<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\RoutineEntry;
use App\Services\RoutineScheduleService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherScheduleController extends Controller
{
    public function __construct(private readonly RoutineScheduleService $schedule) {}

    /**
     * A teacher only ever sees timetable cells assigned to their linked staff
     * profile. The route is read-only; no routine editing endpoints are exposed
     * to the teacher role.
     */
    public function index(Request $request): View
    {
        $staff = $request->user()?->staff;
        $sessionId = $request->filled('session_id')
            ? $request->integer('session_id')
            : current_session()?->id;

        $entries = collect();

        if ($staff) {
            $entries = RoutineEntry::query()
                ->with([
                    'subject',
                    'timeSlot',
                    'routine.class',
                    'routine.section',
                    'routine.academicSession',
                ])
                ->where('staff_id', $staff->id)
                ->whereHas('routine', fn ($query) => $query
                    ->when($sessionId, fn ($inner) => $inner->where('academic_session_id', $sessionId)))
                ->get();
        }

        $entryMap = $entries->keyBy(fn (RoutineEntry $entry) => $this->schedule->cellKey($entry->day, $entry->routine_time_slot_id));

        return view('teacher.schedule', [
            'staff' => $staff,
            'session' => AcademicSession::find($sessionId),
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'selectedSessionId' => $sessionId,
            'days' => $this->schedule->workingDays(),
            'slots' => $this->schedule->activeSlotsForClasses(
                $entries->map(fn (RoutineEntry $entry) => (int) $entry->routine?->class_id)->filter()->unique()->values()->all()
            ),
            'entries' => $entries,
            'entryMap' => $entryMap,
        ]);
    }
}
