<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMarksRequest;
use App\Models\Enrollment;
use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\Mark;
use App\Models\ResultCorrection;
use App\Models\Section;
use App\Models\TeacherAssignment;
use App\Services\AuditLogService;
use App\Services\ExamResultService;
use App\Services\MarksAuthorizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MarksController extends Controller
{
    public function __construct(
        private readonly ExamResultService $results,
        private readonly MarksAuthorizationService $authorization,
        private readonly AuditLogService $audit
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $session = current_session();
        $isAdministrative = $this->authorization->isAdministrative($user);

        $exams = Examination::query()
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->orderByDesc('start_date')
            ->get();

        $exam = $request->filled('exam_id')
            ? $exams->firstWhere('id', $request->integer('exam_id'))
            : $exams->first();

        $examSubjects = $exam
            ? $exam->subjects()->with(['class', 'subject'])->orderBy('class_id')->get()
            : collect();

        $assignments = collect();

        if (! $isAdministrative) {
            $staff = $this->authorization->activeStaff($user);
            $sessionId = $session?->id ?? $exam?->academic_session_id;

            $assignments = $staff && $sessionId
                ? TeacherAssignment::query()
                    ->forSession($sessionId)
                    ->where('staff_id', $staff->id)
                    ->effective()
                    ->get()
                : collect();

            // Only expose papers the teacher is actually assigned to.
            $examSubjects = $examSubjects->filter(fn (ExaminationSubject $paper) => $assignments->contains(
                fn (TeacherAssignment $assignment) => $assignment->class_id === $paper->class_id
                    && $assignment->subject_id === $paper->subject_id
            ))->values();
        }

        $examSubject = $request->filled('examination_subject_id')
            ? $examSubjects->firstWhere('id', $request->integer('examination_subject_id'))
            : null;

        // Where a teacher is only assigned specific sections, never fall back to
        // "all sections", which would expose other teachers' students.
        $sections = Section::ordered()->get();
        $forcedSection = null;

        if ($examSubject && ! $isAdministrative) {
            $paperAssignments = $assignments->filter(
                fn (TeacherAssignment $assignment) => $assignment->class_id === $examSubject->class_id
                    && $assignment->subject_id === $examSubject->subject_id
            );

            if ($paperAssignments->contains(fn (TeacherAssignment $assignment) => $assignment->section_id === null)) {
                $sections = Section::ordered()->get();
            } else {
                $allowedIds = $paperAssignments->pluck('section_id')->filter()->unique()->values();
                $sections = Section::whereIn('id', $allowedIds)->orderBy('name')->get();
                $forcedSection = $sections->first();
            }
        }

        $section = $request->filled('section_id')
            ? $sections->firstWhere('id', $request->integer('section_id'))
            : $forcedSection;

        if ($request->filled('section_id') && ! $section) {
            abort(403, 'You are not assigned to that section.');
        }

        $students = collect();
        $marks = collect();

        if ($examSubject && ($isAdministrative || $assignments->contains(
            fn (TeacherAssignment $assignment) => $assignment->class_id === $examSubject->class_id
                && $assignment->subject_id === $examSubject->subject_id
        ))) {
            $students = Enrollment::query()
                ->with(['student', 'section'])
                ->where('academic_session_id', $examSubject->examination->academic_session_id)
                ->where('class_id', $examSubject->class_id)
                ->where('status', 'active')
                ->when($section, fn ($query) => $query->where('section_id', $section->id))
                ->orderBy('section_id')
                ->orderBy('roll_no')
                ->get();

            $marks = Mark::query()
                ->where('examination_subject_id', $examSubject->id)
                ->whereIn('student_id', $students->pluck('student_id'))
                ->get()
                ->keyBy('student_id');
        }

        if ($examSubject && ! $isAdministrative && $students->isEmpty() && ! $assignments->contains(
            fn (TeacherAssignment $assignment) => $assignment->class_id === $examSubject->class_id
                && $assignment->subject_id === $examSubject->subject_id
        )) {
            abort(403, 'You are not assigned to this paper.');
        }

        return view('academics.marks.index', [
            'session' => $session,
            'exams' => $exams,
            'exam' => $exam,
            'examSubjects' => $examSubjects,
            'examSubject' => $examSubject,
            'sections' => $sections,
            'section' => $section,
            'students' => $students,
            'marks' => $marks,
            'isAdministrative' => $isAdministrative,
            'components' => collect($examSubject?->components ?? [])->values(),
        ]);
    }

    public function store(StoreMarksRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $examSubject = ExaminationSubject::with(['examination', 'class', 'subject'])->findOrFail($data['examination_subject_id']);
        $section = $request->filled('section_id') ? Section::findOrFail($request->integer('section_id')) : null;
        $user = $request->user();

        $this->authorization->authorizeEntry($user, $examSubject, $section);
        $this->authorization->ensureExamIsEditable($examSubject->examination, $user);

        $students = Enrollment::query()
            ->with('student')
            ->where('academic_session_id', $examSubject->examination->academic_session_id)
            ->where('class_id', $examSubject->class_id)
            ->where('status', 'active')
            ->when($section, fn ($query) => $query->where('section_id', $section->id))
            ->get();

        $exam = $examSubject->examination;
        $rules = $this->results->rulesForExam($exam);
        $components = collect($examSubject->components ?? [])->values();
        $saved = 0;
        $corrected = 0;
        $finalizedCorrected = 0;
        $reason = trim((string) ($data['correction_reason'] ?? ''));

        $existingMarks = Mark::query()
            ->where('examination_subject_id', $examSubject->id)
            ->whereIn('student_id', $students->pluck('student_id'))
            ->get()
            ->keyBy('student_id');

        DB::transaction(function () use ($data, $exam, $examSubject, $components, $students, $existingMarks, $rules, $user, $reason, &$saved, &$corrected, &$finalizedCorrected) {
            foreach ($students as $enrollment) {
                $studentId = $enrollment->student_id;
                $value = $data['marks'][$studentId] ?? null;
                $absent = (bool) ($data['absent'][$studentId] ?? false);
                $special = $data['special_status'][$studentId] ?? null;
                $special = $special !== '' ? $special : null;
                $normalizedComponents = null;

                if ($components->isNotEmpty()) {
                    $value = null;
                    $entered = collect($data['components'][$studentId] ?? []);
                    $componentValues = [];
                    $filled = 0;

                    foreach ($components as $index => $component) {
                        $raw = $entered->get($index);
                        if ($raw === null || $raw === '') {
                            $componentValues[$index] = null;

                            continue;
                        }

                        $number = round((float) $raw, 2);
                        $maximum = (float) ($component['full_marks'] ?? 0);

                        if ($number < 0 || $number > $maximum) {
                            throw ValidationException::withMessages([
                                'components' => "{$component['name']} marks must be between 0 and {$maximum}.",
                            ]);
                        }

                        $componentValues[$index] = $number;
                        $filled++;
                    }

                    if ($filled > 0 && $filled < $components->count() && ! $absent && ! $special) {
                        throw ValidationException::withMessages([
                            'components' => 'Enter marks for every component, or mark the student absent or choose a special status.',
                        ]);
                    }

                    if ($filled > 0) {
                        $value = array_sum(array_filter($componentValues, fn ($componentMark) => $componentMark !== null));
                    }

                    if (! $absent && $special !== 'exempt' && $filled > 0) {
                        $normalizedComponents = $componentValues;
                    }
                }

                if (! $absent && ($value === null || $value === '') && ! $special) {
                    continue;
                }

                if ($special === 'absent') {
                    $absent = true;
                }

                $existing = $existingMarks->get($studentId);

                if ($existing) {
                    $this->authorization->ensureCanChangeExistingMark($user, $existing);
                }

                $newMarks = $absent || $special === 'exempt' ? null : ($value === null || $value === '' ? null : (float) $value);
                $existingComponents = collect($existing?->component_marks ?? [])
                    ->map(fn ($componentMark) => $componentMark === null ? null : round((float) $componentMark, 2))
                    ->all();
                $changed = $existing && (
                    ($existing->marks_obtained === null) !== ($newMarks === null)
                    || ($newMarks !== null && abs((float) $existing->marks_obtained - $newMarks) > 0.001)
                    || (bool) $existing->is_absent !== $absent
                    || $existing->special_status !== $special
                    || ($components->isNotEmpty() && $existingComponents !== $normalizedComponents)
                );

                // Saving an unchanged finalized row must not secretly reset its
                // workflow status or create a pointless correction record.
                if ($existing && ! $changed) {
                    continue;
                }

                $finalized = $existing && (
                    in_array($existing->status, ['submitted', 'approved'], true)
                    || $exam->status === 'published'
                );

                if ($finalized && $reason === '') {
                    throw ValidationException::withMessages([
                        'correction_reason' => 'A reason is required when correcting finalized or published marks.',
                    ]);
                }

                if ($existing && $finalized) {
                    $finalizedCorrected++;
                }

                if ($existing && in_array($existing->status, ['submitted', 'approved'], true)) {
                    $corrected++;
                }

                $mark = $this->results->enterMark(
                    $examSubject,
                    $enrollment->student,
                    $value === null || $value === '' ? null : (float) $value,
                    $absent,
                    $user,
                    $rules,
                    $special,
                    null,
                    $normalizedComponents
                );

                // An authorised correction on an already published result is
                // itself final: keep the mark approved and refresh the snapshot
                // instead of sending the result back through drafting.
                if ($finalized && $exam->status === 'published') {
                    $mark->update([
                        'status' => 'approved',
                        'submitted_at' => $mark->submitted_at ?? now(),
                        'submitted_by' => $mark->submitted_by ?? $user->id,
                        'approved_at' => now(),
                        'approved_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }

                if ($existing && $finalized) {
                    ResultCorrection::create([
                        'mark_id' => $existing->id,
                        'old_marks' => $existing->marks_obtained,
                        'new_marks' => $mark->marks_obtained,
                        'old_grade' => $existing->grade,
                        'new_grade' => $mark->grade,
                        'old_status' => $existing->status,
                        'new_status' => $mark->status,
                        'reason' => $reason,
                        'corrected_by' => $user->id,
                    ]);
                }

                $saved++;
            }

            $this->audit->record(
                'marks_entered',
                'Academics',
                "Marks saved for {$examSubject->subject?->name} ({$examSubject->examination?->name})",
                $examSubject,
                null,
                ['students' => $saved, 'administrative_override' => $this->authorization->isAdministrative($user)]
            );

            if ($corrected > 0) {
                $this->audit->record(
                    'marks_corrected',
                    'Academics',
                    "Corrected {$corrected} submitted/approved mark(s) for {$examSubject->subject?->name} ({$examSubject->examination?->name})",
                    $examSubject,
                    ['status' => 'submitted_or_approved'],
                    ['status' => 'draft', 'corrected' => $corrected, 'reason' => $reason, 'corrected_by' => $user->id]
                );
            }
        });

        if ($finalizedCorrected > 0 && $exam->status === 'published') {
            $exam->refresh();
            $snapshotCount = $this->results->publishSnapshots($exam, $user);

            $this->audit->record(
                'published_snapshots_refreshed',
                'Academics',
                "Refreshed {$snapshotCount} published result snapshot(s) after marks correction for {$exam->name}",
                $exam,
                null,
                ['snapshots' => $snapshotCount, 'corrected' => $finalizedCorrected, 'reason' => $reason]
            );
        }

        return redirect()
            ->route('exams.marks-entry', [
                'exam_id' => $examSubject->examination_id,
                'examination_subject_id' => $examSubject->id,
                'section_id' => $section?->id,
            ])
            ->with('success', "Marks saved for {$saved} student(s).".($finalizedCorrected > 0 ? ' Published snapshots were regenerated and the correction was logged.' : ''));
    }

    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'examination_subject_id' => ['required', 'exists:examination_subjects,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
        ]);

        $user = $request->user();
        $examSubject = ExaminationSubject::with(['examination', 'subject'])->findOrFail($data['examination_subject_id']);
        $section = ! empty($data['section_id']) ? Section::find($data['section_id']) : null;

        $this->authorization->authorizeEntry($user, $examSubject, $section);
        $this->authorization->ensureExamIsEditable($examSubject->examination, $user);

        if (! $user->hasPermission('marks.submit') && ! $this->authorization->isAdministrative($user)) {
            abort(403, 'You do not have permission to submit marks.');
        }

        $studentIds = Enrollment::query()
            ->where('academic_session_id', $examSubject->examination->academic_session_id)
            ->where('class_id', $examSubject->class_id)
            ->where('status', 'active')
            ->when($section, fn ($query) => $query->where('section_id', $section->id))
            ->pluck('student_id');

        $submitted = Mark::query()
            ->where('examination_subject_id', $examSubject->id)
            ->whereIn('student_id', $studentIds)
            ->where('status', 'draft')
            ->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'submitted_by' => $user->id,
                'updated_by' => $user->id,
            ]);

        $this->audit->record(
            'marks_submitted',
            'Academics',
            "Submitted {$submitted} mark(s) for {$examSubject->subject?->name}",
            $examSubject,
            ['status' => 'draft'],
            ['status' => 'submitted', 'marks' => $submitted]
        );

        return back()->with('success', "{$submitted} mark(s) submitted for approval.");
    }

    public function approve(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'examination_subject_id' => ['required', 'exists:examination_subjects,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
        ]);

        $user = $request->user();
        abort_unless($user->hasPermission('marks.approve') || $user->isSuperAdmin(), 403, 'You cannot approve marks.');

        $examSubject = ExaminationSubject::with(['examination', 'subject'])->findOrFail($data['examination_subject_id']);
        $section = ! empty($data['section_id']) ? Section::find($data['section_id']) : null;

        $studentIds = Enrollment::query()
            ->where('academic_session_id', $examSubject->examination->academic_session_id)
            ->where('class_id', $examSubject->class_id)
            ->where('status', 'active')
            ->when($section, fn ($query) => $query->where('section_id', $section->id))
            ->pluck('student_id');

        $approved = Mark::query()
            ->where('examination_subject_id', $examSubject->id)
            ->whereIn('student_id', $studentIds)
            ->where('status', 'submitted')
            ->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $user->id,
                'updated_by' => $user->id,
            ]);

        $this->audit->record(
            'marks_approved',
            'Academics',
            "Approved {$approved} mark(s) for {$examSubject->subject?->name}",
            $examSubject,
            ['status' => 'submitted'],
            ['status' => 'approved', 'marks' => $approved]
        );

        return back()->with('success', "{$approved} mark(s) approved and finalized.");
    }
}
