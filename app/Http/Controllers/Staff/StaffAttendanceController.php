<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffAttendanceController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $date = $request->filled('date') ? $request->date('date')?->toDateString() : now()->toDateString();

        $staff = Staff::query()
            ->with(['department', 'designation'])
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        $records = StaffAttendance::query()
            ->whereIn('staff_id', $staff->pluck('id'))
            ->whereDate('attendance_date', $date)
            ->get()
            ->keyBy('staff_id');

        // Staff with approved leave on this date default to "On leave".
        $onLeaveIds = \App\Models\StaffLeave::query()
            ->whereIn('staff_id', $staff->pluck('id'))
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->pluck('staff_id')
            ->all();

        $statuses = $request->filled('status')
            ? collect(StaffAttendance::STATUSES)->only([$request->string('status')->toString()])->all()
            : StaffAttendance::STATUSES;

        $summary = [
            'total' => $staff->count(),
            'present' => $records->where('status', 'present')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'leave' => $records->where('status', 'leave')->count(),
            'unmarked' => max(0, $staff->count() - $records->count()),
        ];

        $holiday = \App\Models\Holiday::forDate($date, 'staff');

        return view('staff.attendance', [
            'staff' => $staff->filter(fn (Staff $member) => ! $request->filled('status')
                || ($records->get($member->id)?->status === $request->string('status')->toString())),
            'records' => $records,
            'onLeaveIds' => $onLeaveIds,
            'date' => $date,
            'departments' => Department::orderBy('name')->get(),
            'statuses' => $statuses,
            'holiday' => $holiday,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['required', 'in:'.implode(',', array_keys(StaffAttendance::STATUSES))],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:255'],
        ]);

        $date = $data['date'];
        $saved = 0;

        if ($holiday = \App\Models\Holiday::forDate($date, 'staff')) {
            return back()->withErrors([
                'date' => $holiday['name'].' — staff attendance is not taken on a holiday or weekly off.',
            ])->withInput();
        }

        DB::transaction(function () use ($data, $date, $request, &$saved) {
            foreach ($data['statuses'] as $staffId => $status) {
                $staff = Staff::query()->find((int) $staffId);

                if (! $staff) {
                    continue;
                }

                $record = StaffAttendance::query()->updateOrCreate(
                    ['staff_id' => $staff->id, 'attendance_date' => $date],
                    [
                        'status' => $status,
                        'note' => $data['notes'][$staffId] ?? null,
                        'marked_by' => $request->user()->id,
                    ]
                );

                $saved++;
            }

            $this->audit->record(
                'staff_attendance_marked',
                'Staff',
                "Staff attendance saved for {$date} ({$saved} record(s))",
                null,
                null,
                ['date' => $date, 'records' => $saved]
            );
        });

        return redirect()
            ->route('staff.attendance.index', ['date' => $date])
            ->with('success', "Staff attendance saved for {$date}.");
    }
}
