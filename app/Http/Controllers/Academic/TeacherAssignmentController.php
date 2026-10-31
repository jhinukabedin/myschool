<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeacherAssignmentController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $session = current_session();

        $assignments = TeacherAssignment::query()
            ->with(['academicSession', 'class', 'section', 'subject', 'staff'])
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->when($request->filled('session_id'), fn ($query) => $query->where('academic_session_id', $request->integer('session_id')))
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->integer('class_id')))
            ->when($request->filled('section_id'), fn ($query) => $query->where('section_id', $request->integer('section_id')))
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('staff_id'), fn ($query) => $query->where('staff_id', $request->integer('staff_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->orderBy('class_id')
            ->orderBy('section_id')
            ->orderBy('subject_id')
            ->paginate(30)
            ->withQueryString();

        return view('academics.assignments.index', [
            'assignments' => $assignments,
            'session' => $session,
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'classes' => SchoolClass::ordered()->get(),
            'sections' => Section::orderBy('name')->get(),
            'subjects' => Subject::orderBy('name')->get(),
            'teachers' => Staff::where('status', 'active')->orderBy('first_name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('academics.assignments.form', $this->formData(new TeacherAssignment([
            'academic_session_id' => current_session()?->id,
            'starts_on' => now()->toDateString(),
            'is_active' => true,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->assertNoConflict($data);

        $assignment = TeacherAssignment::create($data);

        $this->audit->record(
            'teacher_assignment_created',
            'Academics',
            "Assigned {$assignment->staff?->full_name} to {$assignment->class?->name} / {$assignment->subject?->name}",
            $assignment,
            null,
            $assignment->only(['academic_session_id', 'class_id', 'section_id', 'subject_id', 'staff_id', 'is_active'])
        );

        return redirect()->route('assignments.index')->with('success', 'Teacher assignment created.');
    }

    public function edit(TeacherAssignment $assignment): View
    {
        return view('academics.assignments.form', $this->formData($assignment->load(['class', 'section', 'subject', 'staff'])));
    }

    public function update(Request $request, TeacherAssignment $assignment): RedirectResponse
    {
        $data = $this->validated($request);
        $this->assertNoConflict($data, $assignment->id);

        $before = $assignment->only(['academic_session_id', 'class_id', 'section_id', 'subject_id', 'staff_id', 'is_active', 'starts_on', 'ends_on']);
        $assignment->update($data);

        $this->audit->record(
            'teacher_assignment_updated',
            'Academics',
            "Updated assignment #{$assignment->id}",
            $assignment,
            $before,
            $assignment->only(['academic_session_id', 'class_id', 'section_id', 'subject_id', 'staff_id', 'is_active', 'starts_on', 'ends_on'])
        );

        return redirect()->route('assignments.index')->with('success', 'Teacher assignment updated.');
    }

    public function toggle(TeacherAssignment $assignment): RedirectResponse
    {
        $before = $assignment->is_active;
        $assignment->update(['is_active' => ! $assignment->is_active]);

        $this->audit->record(
            'teacher_assignment_status_changed',
            'Academics',
            ($assignment->is_active ? 'Activated' : 'Deactivated')." teacher assignment #{$assignment->id}",
            $assignment,
            ['is_active' => $before],
            ['is_active' => $assignment->is_active]
        );

        return back()->with('success', $assignment->is_active
            ? 'Assignment activated. Historical records are preserved.'
            : 'Assignment deactivated. Historical records are preserved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'staff_id' => ['required', 'exists:staff,id'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['section_id'] = $data['section_id'] ?? null;

        return $data;
    }

    /**
     * Reject duplicate / conflicting active assignments unless co-teaching has
     * been explicitly enabled in school settings.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function assertNoConflict(array $data, ?int $ignoreId = null): void
    {
        $query = TeacherAssignment::query()
            ->where('academic_session_id', $data['academic_session_id'])
            ->where('class_id', $data['class_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('is_active', true)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId));

        if (empty($data['section_id'])) {
            // A whole-class assignment overlaps every section of that class.
        } else {
            $query->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $data['section_id']));
        }

        $conflicts = $query->get();

        if ($conflicts->contains(fn (TeacherAssignment $assignment) => $assignment->staff_id === (int) $data['staff_id'])) {
            throw ValidationException::withMessages([
                'staff_id' => 'This teacher already has an identical active assignment.',
            ]);
        }

        $coTeaching = filter_var(school_setting('allow_co_teaching', false), FILTER_VALIDATE_BOOL);

        if ($conflicts->isNotEmpty() && ! $coTeaching) {
            throw ValidationException::withMessages([
                'section_id' => 'An active assignment already exists for this session, class, section and subject. Enable co-teaching in school settings to add another teacher.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(TeacherAssignment $assignment): array
    {
        return [
            'assignment' => $assignment,
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'classes' => SchoolClass::ordered()->get(),
            'sections' => Section::orderBy('name')->get(),
            'subjects' => Subject::orderBy('name')->get(),
            'teachers' => Staff::where('status', 'active')->orderBy('first_name')->get(),
        ];
    }
}
