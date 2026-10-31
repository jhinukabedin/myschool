<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(Request $request): View
    {
        $session = current_session();
        $date = $request->input('date', now()->toDateString());
        $class = $request->filled('class_id') ? SchoolClass::find($request->integer('class_id')) : null;
        $section = $request->filled('section_id') ? Section::find($request->integer('section_id')) : null;

        $students = collect();
        $records = collect();

        if ($session && $class && $section) {
            $students = Enrollment::query()
                ->with('student')
                ->where('academic_session_id', $session->id)
                ->where('class_id', $class->id)
                ->where('section_id', $section->id)
                ->where('status', 'active')
                ->orderBy('roll_no')
                ->get();

            $records = AttendanceRecord::query()
                ->where('academic_session_id', $session->id)
                ->where('class_id', $class->id)
                ->where('section_id', $section->id)
                ->whereDate('attendance_date', $date)
                ->get()
                ->keyBy('student_id');
        }

        return view('academics.attendance.index', [
            'session' => $session,
            'date' => $date,
            'class' => $class,
            'section' => $section,
            'students' => $students,
            'records' => $records,
            'classes' => SchoolClass::with('sections')->ordered()->get(),
            'sections' => Section::orderBy('name')->get(),
            'summary' => $session ? AttendanceRecord::query()
                ->where('academic_session_id', $session->id)
                ->whereDate('attendance_date', $date)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status') : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'class_id' => ['required', 'exists:classes,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', 'in:present,absent,late,excused'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:255'],
        ]);

        $session = current_session();
        abort_unless($session, 404, 'No academic session is configured.');

        $class = SchoolClass::findOrFail($data['class_id']);
        $section = Section::findOrFail($data['section_id']);

        $saved = $this->attendance->mark(
            $session,
            $class,
            $section,
            $data['date'],
            $data['statuses'],
            $request->user(),
            $data['notes'] ?? []
        );

        return redirect()
            ->route('attendance.index', ['date' => $data['date'], 'class_id' => $class->id, 'section_id' => $section->id])
            ->with('success', "Attendance saved for {$saved} student(s).");
    }
}
