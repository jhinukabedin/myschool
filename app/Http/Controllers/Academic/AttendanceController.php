<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\AttendanceAuthorizationService;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly AttendanceAuthorizationService $authorization,
    ) {}

    public function index(Request $request): View
    {
        $session = current_session();
        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
        ]);
        $date = $filters['date'] ?? now()->toDateString();
        $class = isset($filters['class_id']) ? SchoolClass::findOrFail($filters['class_id']) : null;
        $section = isset($filters['section_id']) ? Section::findOrFail($filters['section_id']) : null;
        $user = $request->user();
        $classSectionMap = $this->authorization->classSectionMap($user, $session, $date);

        // Academic calendar: no attendance is expected on a holiday or weekly off.
        $holiday = \App\Models\Holiday::forDate($date, 'students');

        if ($section && ! $class) {
            abort(422, 'Select a class before selecting a section.');
        }
        if ($class && $section && ! $class->sections()->whereKey($section->id)->exists()) {
            abort(422, 'The selected section does not belong to this class.');
        }
        if ($class && ! $section && ! $this->authorization->isAdministrator($user) && ! $classSectionMap->has($class->id)) {
            abort(403, 'You are not assigned to this class.');
        }
        if ($class && $section && ! $this->authorization->canAccess($user, $session, $class, (int) $section->id, $date)) {
            abort(403, 'You are not assigned to this class and section.');
        }

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
                ->with('markedBy')
                ->where('academic_session_id', $session->id)
                ->where('class_id', $class->id)
                ->where('section_id', $section->id)
                ->whereDate('attendance_date', $date)
                ->get()
                ->keyBy('student_id');
        }

        $classes = SchoolClass::with('sections')->ordered()->get();
        if (! $this->authorization->isAdministrator($user)) {
            $classes = $classes->filter(fn (SchoolClass $option) => $classSectionMap->has($option->id))->values();
            $classes->each(fn (SchoolClass $option) => $option->setRelation(
                'sections',
                $option->sections->whereIn('id', $classSectionMap->get($option->id, []))->values()
            ));
        }

        $sectionIds = $class
            ? ($classSectionMap->get($class->id, []))
            : $classSectionMap->flatten()->unique()->values()->all();
        $sections = Section::query()->whereIn('id', $sectionIds)->ordered()->get();

        $summaryQuery = $session ? AttendanceRecord::query()
            ->where('academic_session_id', $session->id)
            ->whereDate('attendance_date', $date) : null;
        if ($summaryQuery) {
            if (! $this->authorization->isAdministrator($user)) {
                $this->authorization->constrainQuery($summaryQuery, $user, $session, $date, $date);
            }
            if ($class) {
                $summaryQuery->where('class_id', $class->id);
            }
            if ($section) {
                $summaryQuery->where('section_id', $section->id);
            }
        }

        return view('academics.attendance.index', [
            'session' => $session,
            'date' => $date,
            'class' => $class,
            'section' => $section,
            'students' => $students,
            'records' => $records,
            'classes' => $classes,
            'sections' => $sections,
            'holiday' => $holiday,
            'summary' => $summaryQuery ? $summaryQuery
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status') : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'class_id' => ['required', 'exists:classes,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', 'in:present,absent,late,excused'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:255'],
        ]);

        $session = current_session();
        abort_unless($session, 404, 'No academic session is configured.');

        if ($holiday = \App\Models\Holiday::forDate($data['date'], 'students')) {
            return back()->withErrors([
                'date' => $holiday['name'].' — attendance is not taken on a holiday or weekly off.',
            ])->withInput();
        }

        $class = SchoolClass::findOrFail($data['class_id']);
        $section = Section::findOrFail($data['section_id']);

        if (! $class->sections()->whereKey($section->id)->exists()) {
            return back()->withErrors(['section_id' => 'This section is not linked to the selected class.'])->withInput();
        }
        abort_unless($this->authorization->canAccess($request->user(), $session, $class, (int) $section->id, $data['date']), 403,
            'You are not assigned to take attendance for this class and section.');

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
