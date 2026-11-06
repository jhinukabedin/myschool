<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\SalaryPayment;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffLeave;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffPortalController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function dashboard(Request $request): View
    {
        $staff = $this->staff($request);
        $this->authorizeOwner($staff, $request);

        $month = now()->format('Y-m');
        $payroll = Payroll::query()->where('staff_id', $staff->id)->where('month', $month)->first();

        $attendance = StaffAttendance::query()
            ->where('staff_id', $staff->id)
            ->whereBetween('attendance_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $leave = [
            'pending' => StaffLeave::where('staff_id', $staff->id)->where('status', 'pending')->count(),
            'approved' => StaffLeave::where('staff_id', $staff->id)->where('status', 'approved')->count(),
            'days' => (int) StaffLeave::where('staff_id', $staff->id)->where('status', 'approved')->sum('days'),
        ];

        return view('portal.staff.dashboard', [
            'staff' => $staff,
            'payroll' => $payroll,
            'attendance' => $attendance,
            'leave' => $leave,
            'recentLeaves' => StaffLeave::where('staff_id', $staff->id)->latest('start_date')->limit(5)->get(),
            'recentPayments' => SalaryPayment::query()
                ->with('payroll')
                ->whereHas('payroll', fn ($query) => $query->where('staff_id', $staff->id))
                ->latest('paid_at')
                ->limit(5)
                ->get(),
            'holiday' => \App\Models\Holiday::forDate(now(), 'staff'),
        ]);
    }

    public function profile(Request $request): View
    {
        return view('portal.staff.profile', ['staff' => $this->staff($request)]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $staff = $this->staff($request);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('photo')) {
            if ($staff->photo_path) {
                Storage::disk('public')->delete($staff->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('staff', 'public');
        }

        unset($data['photo']);
        $before = $staff->only(['phone', 'email', 'address']);
        $staff->update($data);

        $this->audit->record('staff_profile_self_updated', 'Staff', "{$staff->full_name} updated their own contact details", $staff, $before, $staff->only(['phone', 'email', 'address']));

        return back()->with('success', 'Profile updated.');
    }

    public function leaves(Request $request): View
    {
        $staff = $this->staff($request);

        return view('portal.staff.leaves', [
            'staff' => $staff,
            'leaves' => StaffLeave::where('staff_id', $staff->id)->orderByDesc('start_date')->paginate(15),
            'types' => StaffLeave::TYPES,
            'summary' => [
                'pending' => StaffLeave::where('staff_id', $staff->id)->where('status', 'pending')->count(),
                'approved' => StaffLeave::where('staff_id', $staff->id)->where('status', 'approved')->count(),
                'days' => (int) StaffLeave::where('staff_id', $staff->id)->where('status', 'approved')->sum('days'),
            ],
        ]);
    }

    public function storeLeave(Request $request): RedirectResponse
    {
        $staff = $this->staff($request);

        $data = $request->validate([
            'leave_type' => ['required', 'in:'.implode(',', array_keys(StaffLeave::TYPES))],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();

        $overlap = StaffLeave::query()
            ->where('staff_id', $staff->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => 'You already have leave covering those dates.',
            ]);
        }

        $leave = StaffLeave::create($data + [
            'staff_id' => $staff->id,
            'days' => (int) $start->diffInDays($end) + 1,
            'status' => 'pending',
        ]);

        $this->audit->record('staff_leave_requested', 'Staff', "{$staff->full_name} applied for {$leave->type_label} ({$leave->days} day(s))", $leave, null, $leave->getAttributes());

        return back()->with('success', 'Leave application submitted for approval.');
    }

    public function payslips(Request $request): View
    {
        $staff = $this->staff($request);

        return view('portal.staff.payslips', [
            'staff' => $staff,
            'payrolls' => Payroll::query()
                ->where('staff_id', $staff->id)
                ->where('status', '!=', 'cancelled')
                ->orderByDesc('month')
                ->paginate(15),
        ]);
    }

    public function payslip(Request $request, Payroll $payroll): View
    {
        $staff = $this->staff($request);
        abort_unless((int) $payroll->staff_id === (int) $staff->id, 403);

        $payroll->load(['staff.department', 'staff.designation', 'items', 'payment.paidBy', 'payments.paidBy', 'processedBy', 'academicSession']);

        return view('payroll.slip', ['payroll' => $payroll]);
    }

    public function payments(Request $request): View
    {
        $staff = $this->staff($request);

        return view('portal.staff.payments', [
            'staff' => $staff,
            'payments' => SalaryPayment::query()
                ->with('payroll')
                ->whereHas('payroll', fn ($query) => $query->where('staff_id', $staff->id))
                ->latest('paid_at')
                ->paginate(20),
        ]);
    }

    private function staff(Request $request): Staff
    {
        $staff = $request->user()->staff;

        abort_unless($staff, 403, 'No staff profile is linked to your account.');

        return $staff->loadMissing('qualifications');
    }

    private function authorizeOwner(Staff $staff, Request $request): void
    {
        abort_unless((int) $staff->user_id === (int) $request->user()->id, 403);
    }
}
