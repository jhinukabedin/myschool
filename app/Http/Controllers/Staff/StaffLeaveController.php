<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffLeave;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffLeaveController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $leaves = StaffLeave::query()
            ->with(['staff.department', 'staff.designation', 'approvedBy'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('staff_id'), fn ($query) => $query->where('staff_id', $request->integer('staff_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('end_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('start_date', '<=', $request->date('to')))
            ->orderByDesc('start_date')
            ->paginate(20)
            ->withQueryString();

        $base = StaffLeave::query();

        $summary = [
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
            'days_approved' => (int) (clone $base)->where('status', 'approved')->sum('days'),
            'on_leave_today' => (clone $base)->where('status', 'approved')
                ->whereDate('start_date', '<=', now()->toDateString())
                ->whereDate('end_date', '>=', now()->toDateString())
                ->count(),
        ];

        return view('staff.leaves', [
            'leaves' => $leaves,
            'staff' => Staff::query()->where('status', 'active')->orderBy('first_name')->get(),
            'types' => StaffLeave::TYPES,
            'statuses' => StaffLeave::STATUSES,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'staff_id' => ['required', 'exists:staff,id'],
            'leave_type' => ['required', 'in:'.implode(',', array_keys(StaffLeave::TYPES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $days = (int) $start->diffInDays($end) + 1;

        // Overlapping pending/approved leave for the same staff member.
        $overlap = StaffLeave::query()
            ->where('staff_id', $data['staff_id'])
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => 'This staff member already has leave covering those dates.',
            ]);
        }

        $leave = StaffLeave::create($data + [
            'days' => $days,
            'status' => 'pending',
        ]);

        $this->audit->record(
            'staff_leave_requested',
            'Staff',
            "{$leave->staff?->full_name} requested {$leave->type_label} for {$days} day(s)",
            $leave,
            null,
            $leave->getAttributes()
        );

        return back()->with('success', 'Leave recorded and pending approval.');
    }

    public function approve(Request $request, StaffLeave $leave): RedirectResponse
    {
        if ($leave->status !== 'pending') {
            return back()->with('error', 'Only pending leave can be approved.');
        }

        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:255']]);

        $leave->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'decision_note' => $data['decision_note'] ?? null,
        ]);

        $this->audit->record('staff_leave_approved', 'Staff', "Leave approved for {$leave->staff?->full_name}", $leave, ['status' => 'pending'], ['status' => 'approved']);

        return back()->with('success', 'Leave approved.');
    }

    public function reject(Request $request, StaffLeave $leave): RedirectResponse
    {
        if ($leave->status !== 'pending') {
            return back()->with('error', 'Only pending leave can be rejected.');
        }

        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:255']]);

        $leave->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'decision_note' => $data['decision_note'] ?? null,
        ]);

        $this->audit->record('staff_leave_rejected', 'Staff', "Leave rejected for {$leave->staff?->full_name}", $leave, ['status' => 'pending'], ['status' => 'rejected']);

        return back()->with('success', 'Leave rejected.');
    }

    public function cancel(StaffLeave $leave): RedirectResponse
    {
        if (! in_array($leave->status, ['pending', 'approved'], true)) {
            return back()->with('error', 'This leave record cannot be cancelled.');
        }

        $before = $leave->only(['status']);
        $leave->update(['status' => 'cancelled']);

        $this->audit->record('staff_leave_cancelled', 'Staff', "Leave cancelled for {$leave->staff?->full_name}", $leave, $before, ['status' => 'cancelled']);

        return back()->with('success', 'Leave cancelled.');
    }
}
