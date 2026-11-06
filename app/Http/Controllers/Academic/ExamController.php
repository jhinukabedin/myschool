<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExamRequest;
use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\ClassSubjectMarkOverride;
use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\GradingScheme;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SubjectMarkDefault;
use App\Services\AuditLogService;
use App\Services\ExamResultService;
use App\Services\MarksDistributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExamController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly ExamResultService $results,
        private readonly MarksDistributionService $distributions
    ) {}

    public function index(Request $request): View
    {
        $session = current_session();

        $exams = Examination::query()
            ->with('academicSession')
            ->withCount('subjects')
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->orderByDesc('start_date')
            ->paginate(12)
            ->withQueryString();

        return view('academics.exams.index', [
            'exams' => $exams,
            'session' => $session,
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
        ]);
    }

    public function create(): View
    {
        $exam = new Examination([
                'academic_session_id' => current_session()?->id,
                'status' => 'draft',
                'exam_type' => 'term',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(7)->toDateString(),
            ]);

        return view('academics.exams.form', [
            'exam' => $exam,
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'gradingSchemes' => GradingScheme::query()->active()->orderBy('name')->get(),
            ...$this->distributionFormData($exam),
        ]);
    }

    public function store(StoreExamRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->guardPublicationStatus($data);

        $exam = DB::transaction(function () use ($data, $request) {
            $exam = Examination::create($data);
            $this->saveExamDistributions($request, $exam);

            return $exam;
        });

        $this->audit->record(
            'exam_created',
            'Academics',
            "Examination {$exam->name} created",
            $exam,
            null,
            $exam->getAttributes()
        );

        return redirect()->route('exams.show', $exam)->with('success', 'Examination created. Add subjects and the exam schedule next.');
    }

    public function show(Examination $exam): View
    {
        $exam->load(['subjects.class', 'subjects.subject', 'academicSession', 'gradingScheme']);
        $classIds = $exam->subjects->pluck('class_id')->unique();

        return view('academics.exams.show', [
            'exam' => $exam,
            'classes' => SchoolClass::with('sections')->ordered()->get(),
            'subjects' => Subject::orderBy('name')->get(),
            'classSubjects' => ClassSubject::query()
                ->with('subject')
                ->where('academic_session_id', $exam->academic_session_id)
                ->when($classIds->isNotEmpty(), fn ($query) => $query->whereIn('class_id', $classIds))
                ->get(),
            'marksCount' => $exam->subjects()->withCount('marks')->get()->sum('marks_count'),
            'snapshotCount' => $exam->resultSnapshots()->count(),
            'pendingMarks' => $exam->subjects()->withCount(['marks as pending_marks_count' => fn ($query) => $query->where('status', '!=', 'approved')])->get()->sum('pending_marks_count'),
        ]);
    }

    public function edit(Examination $exam): View
    {
        return view('academics.exams.form', [
            'exam' => $exam,
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'gradingSchemes' => GradingScheme::query()->active()->where(fn ($query) => $query
                ->whereNull('academic_session_id')
                ->orWhere('academic_session_id', $exam->academic_session_id))->orderBy('name')->get(),
            ...$this->distributionFormData($exam),
        ]);
    }

    public function update(StoreExamRequest $request, Examination $exam): RedirectResponse
    {
        $data = $request->validated();
        $this->guardPublicationStatus($data, $exam);

        if ((int) $data['academic_session_id'] !== (int) $exam->academic_session_id && $exam->subjects()->exists()) {
            throw ValidationException::withMessages(['academic_session_id' => 'The academic session cannot be changed after subjects have been added to the exam.']);
        }

        $before = $exam->getAttributes();
        DB::transaction(function () use ($request, $exam, $data) {
            $exam->update($data);
            $this->saveExamDistributions($request, $exam);
        });

        $this->audit->record(
            'exam_updated',
            'Academics',
            "Examination {$exam->name} updated",
            $exam,
            $before,
            $exam->fresh()->getAttributes()
        );

        return redirect()->route('exams.show', $exam)->with('success', 'Examination updated.');
    }

    public function destroy(Examination $exam): RedirectResponse
    {
        if ($exam->status === 'published') {
            return back()->with('error', 'Published examinations cannot be deleted because results may be visible. Keep the record for reporting.');
        }

        $marks = $exam->subjects()->withCount('marks')->get()->sum('marks_count');

        if ($marks > 0) {
            return back()->with('error', 'Marks exist for this examination. Remove cannot proceed because results must be preserved.');
        }

        $old = $exam->getAttributes();

        DB::transaction(function () use ($exam) {
            $exam->subjects()->delete();
            $exam->delete();
        });

        $this->audit->record(
            'exam_deleted',
            'Academics',
            "Examination {$exam->name} deleted",
            null,
            $old,
            null
        );

        return redirect()->route('exams.index')->with('success', 'Examination deleted.');
    }

    public function schedule(Request $request): View
    {
        $session = current_session();

        $subjects = ExaminationSubject::query()
            ->with(['examination', 'class', 'subject'])
            ->when($session, fn ($query) => $query->whereHas('examination', fn ($q) => $q
                ->where('academic_session_id', $session->id)))
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->integer('class_id')))
            ->whereNotNull('exam_date')
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        return view('academics.exams.schedule', [
            'subjects' => $subjects,
            'session' => $session,
            'classes' => SchoolClass::ordered()->get(),
        ]);
    }

    public function publish(Request $request, Examination $exam): RedirectResponse
    {
        if ($exam->subjects()->count() === 0) {
            return back()->with('error', 'Add at least one subject before publishing the examination.');
        }

        $subjectIds = $exam->subjects()->pluck('id');
        $markCount = Mark::query()->whereIn('examination_subject_id', $subjectIds)->count();

        if ($markCount === 0) {
            return back()->with('error', 'Enter and approve marks before publishing results. No marks exist for this examination.');
        }

        $unapproved = Mark::query()
            ->whereIn('examination_subject_id', $subjectIds)
            ->where('status', '!=', 'approved')
            ->count();

        if ($unapproved > 0) {
            return back()->with('error', "{$unapproved} mark row(s) are still draft or submitted. Approve all marks before publishing results.");
        }

        try {
            DB::transaction(function () use ($request, $exam) {
                $before = $exam->only(['status', 'result_published_at', 'result_locked_at', 'grading_scheme_id']);

                // If this exam was published before, reuse the rules in the
                // original snapshots so corrections cannot silently regrade it.
                $rules = $this->results->rulesForPublishedExam($exam);
                $snapshotCount = $this->results->publishSnapshots($exam, $request->user(), $rules);

                $exam->update([
                    'status' => 'published',
                    'result_published_at' => $exam->result_published_at ?? now(),
                    'result_locked_at' => now(),
                    'published_by' => $exam->published_by ?? $request->user()->id,
                    'locked_by' => $request->user()->id,
                    'result_note' => $request->input('result_note'),
                ]);

                $this->audit->record(
                    'exam_published',
                    'Academics',
                    "Results published for {$exam->name}; {$snapshotCount} result snapshot(s) generated",
                    $exam,
                    $before,
                    [
                        'status' => 'published',
                        'snapshots' => $snapshotCount,
                        'grading_scheme_id' => $exam->grading_scheme_id,
                        'result_published_at' => optional($exam->result_published_at)->toDateTimeString(),
                    ]
                );
            });
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first());
        }

        return back()->with('success', 'Results published, locked and snapshotted for authorised portals.');
    }

    public function unpublish(Request $request, Examination $exam): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $before = $exam->only(['status', 'result_published_at', 'result_locked_at']);

        DB::transaction(function () use ($request, $exam, $data, $before) {
            $exam->update([
                'status' => 'completed',
                'result_published_at' => null,
                'result_locked_at' => null,
                'locked_by' => null,
                'result_note' => $data['reason'],
            ]);

            $this->audit->record(
                'exam_unpublished',
                'Academics',
                "Results unpublished for {$exam->name}: {$data['reason']}",
                $exam,
                $before,
                ['status' => 'completed', 'reason' => $data['reason'], 'by' => $request->user()->id]
            );
        });

        return back()->with('success', 'Results hidden from portals. Marks can now be corrected; snapshots are retained for the audit trail.');
    }

    /**
     * Publication and reopening have dedicated, audited actions. The edit form
     * must not silently make results live or hide them.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardPublicationStatus(array $data, ?Examination $exam = null): void
    {
        $requested = $data['status'] ?? null;
        $alreadyPublished = $exam?->result_published_at !== null;

        if ($requested === 'published' && ! $alreadyPublished) {
            throw ValidationException::withMessages([
                'status' => 'Approve the marks, then use “Publish & lock results” on the examination page.',
            ]);
        }

        if ($alreadyPublished && $requested !== 'published') {
            throw ValidationException::withMessages([
                'status' => 'Use “Unpublish and reopen” on the examination page so the change is recorded with a reason.',
            ]);
        }
    }

    private function distributionFormData(Examination $exam): array
    {
        $classes = SchoolClass::ordered()->get();
        $selectedSessionId = (int) old('academic_session_id', $exam->academic_session_id);
        $assigned = ClassSubject::query()
            ->with(['subject'])
            ->whereHas('subject')
            ->orderBy('class_id')->orderBy('subject_id')->get();
        $existing = $exam->exists
            ? $exam->subjects()->with('subject')->get()->keyBy(fn (ExaminationSubject $row) => $row->class_id.'_'.$row->subject_id)
            : collect();
        $globalDefaults = SubjectMarkDefault::query()->get()->keyBy('subject_id');
        $classOverrides = ClassSubjectMarkOverride::query()->get()->keyBy(fn ($row) => $row->class_id.'_'.$row->subject_id);
        $defaults = [];
        $configs = [];

        foreach ($assigned as $classSubject) {
            $subjectKey = $classSubject->class_id.'_'.$classSubject->subject_id;
            $key = $classSubject->academic_session_id.'_'.$subjectKey;
            $resolved = $this->distributions->resolve($classSubject->class_id, $classSubject->subject, $classOverrides, $globalDefaults);
            $defaults[$key] = [
                'session_id' => (int) $classSubject->academic_session_id,
                'class_id' => (int) $classSubject->class_id,
                'subject_id' => (int) $classSubject->subject_id,
                'subject_name' => $classSubject->subject->name,
                'full_marks' => $resolved['full_marks'],
                'pass_marks' => $resolved['pass_marks'],
                'components' => $resolved['components'],
                'source' => $resolved['source'],
                'exam_date' => null,
                'start_time' => null,
                'duration_minutes' => null,
            ];
            $snapshot = (int) $classSubject->academic_session_id === (int) $exam->academic_session_id ? $existing->get($subjectKey) : null;
            $configs[$key] = $snapshot ? [
                ...$defaults[$key],
                'full_marks' => (float) $snapshot->full_marks,
                'pass_marks' => (float) $snapshot->pass_marks,
                'components' => array_values($snapshot->components ?? []),
                'source' => 'Exam snapshot',
                'exam_date' => $snapshot->exam_date?->toDateString(),
                'start_time' => $snapshot->start_time ? substr((string) $snapshot->start_time, 0, 5) : null,
                'duration_minutes' => $snapshot->duration_minutes,
            ] : $defaults[$key];
        }

        foreach ($existing as $subjectKey => $snapshot) {
            $key = $exam->academic_session_id.'_'.$subjectKey;
            if (isset($configs[$key])) {
                continue;
            }
            $snapshot->loadMissing('subject');
            $fallback = $this->distributions->resolve($snapshot->class_id, $snapshot->subject, $classOverrides, $globalDefaults);
            $defaults[$key] = [
                'session_id' => (int) $exam->academic_session_id,
                'class_id' => (int) $snapshot->class_id,
                'subject_id' => (int) $snapshot->subject_id,
                'subject_name' => $snapshot->subject?->name ?? 'Subject',
                ...$fallback,
                'exam_date' => null, 'start_time' => null, 'duration_minutes' => null,
            ];
            $configs[$key] = [
                ...$defaults[$key],
                'full_marks' => (float) $snapshot->full_marks,
                'pass_marks' => (float) $snapshot->pass_marks,
                'components' => array_values($snapshot->components ?? []),
                'source' => 'Exam snapshot',
                'exam_date' => $snapshot->exam_date?->toDateString(),
                'start_time' => $snapshot->start_time ? substr((string) $snapshot->start_time, 0, 5) : null,
                'duration_minutes' => $snapshot->duration_minutes,
            ];
        }

        $oldDistribution = old('distribution');
        if (is_array($oldDistribution)) {
            foreach ($oldDistribution as $classId => $subjects) {
                foreach ($subjects as $subjectId => $row) {
                    $key = $selectedSessionId.'_'.$classId.'_'.$subjectId;
                    if (isset($configs[$key])) {
                        $configs[$key] = [...$configs[$key], ...$row];
                    }
                }
            }
        }

        $selectedClasses = old('selected_classes', $existing->pluck('class_id')->unique()->values()->all());
        $selectedClasses = array_map('intval', (array) $selectedClasses);

        return [
            'classes' => $classes,
            'distributionDefaults' => $defaults,
            'distributionConfigs' => $configs,
            'selectedClasses' => $selectedClasses,
            'selectedSessionId' => $selectedSessionId,
            'existingMarksCount' => $exam->exists ? $exam->subjects()->withCount('marks')->get()->sum('marks_count') : 0,
            'distributionLocked' => $exam->exists && ($exam->result_published_at !== null || $exam->resultSnapshots()->exists()),
        ];
    }

    private function saveExamDistributions(Request $request, Examination $exam): void
    {
        $data = $request->validate([
            'selected_classes' => ['nullable', 'array'],
            'selected_classes.*' => ['integer', 'exists:classes,id'],
            'distribution' => ['nullable', 'array'],
            'distribution.*.*.full_marks' => ['required', 'numeric', 'gt:0', 'max:999'],
            'distribution.*.*.pass_marks' => ['required', 'numeric', 'min:0', 'max:999'],
            'distribution.*.*.components' => ['nullable', 'array'],
            'distribution.*.*.components.*.name' => ['nullable', 'string', 'max:80'],
            'distribution.*.*.components.*.full_marks' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'distribution.*.*.components.*.pass_marks' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'distribution.*.*.components.*.requires_pass' => ['nullable', 'boolean'],
            'distribution.*.*.exam_date' => ['nullable', 'date'],
            'distribution.*.*.start_time' => ['nullable', 'date_format:H:i'],
            'distribution.*.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'confirm_distribution_change' => ['nullable', 'boolean'],
        ]);
        $selectedClassIds = collect($data['selected_classes'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $entries = $data['distribution'] ?? [];

        if ($entries === []) {
            return;
        }

        $assigned = ClassSubject::query()
            ->where('academic_session_id', $exam->academic_session_id)
            ->whereIn('class_id', $selectedClassIds)
            ->get()->keyBy(fn (ClassSubject $row) => $row->class_id.'_'.$row->subject_id);

        foreach ($entries as $classId => $subjects) {
            foreach ($subjects as $subjectId => $entry) {
                $key = $classId.'_'.$subjectId;
                $isExistingExamPaper = $exam->subjects()->where('class_id', $classId)->where('subject_id', $subjectId)->exists();
                if (! $selectedClassIds->contains((int) $classId) || (! $assigned->has($key) && ! $isExistingExamPaper)) {
                    throw ValidationException::withMessages(['distribution' => 'Every configured subject must belong to a selected class in the examination session.']);
                }

                $config = $this->distributions->normalize($entry);
                $examSubject = $exam->subjects()->where('class_id', $classId)->where('subject_id', $subjectId)->first();
                $existingConfig = $examSubject ? $this->distributions->normalize([
                    'full_marks' => $examSubject->full_marks,
                    'pass_marks' => $examSubject->pass_marks,
                    'components' => $examSubject->components ?? [],
                ]) : null;
                $changed = $existingConfig && $existingConfig !== $config;

                if ($changed && ($exam->result_published_at !== null || $exam->resultSnapshots()->exists())) {
                    throw ValidationException::withMessages(['distribution' => 'This examination has published result snapshots. Its stored marks distribution cannot be changed.']);
                }

                if ($changed && $examSubject->marks()->exists() && ! (bool) ($data['confirm_distribution_change'] ?? false)) {
                    throw ValidationException::withMessages(['confirm_distribution_change' => 'Marks already exist. Confirm the marks distribution change before saving.']);
                }

                $exam->subjects()->updateOrCreate(
                    ['class_id' => $classId, 'subject_id' => $subjectId],
                    [
                        ...$config,
                        'exam_date' => $entry['exam_date'] ?? null,
                        'start_time' => $entry['start_time'] ?? null,
                        'duration_minutes' => $entry['duration_minutes'] ?? null,
                    ]
                );
            }
        }
    }

    public function storeSubject(Request $request, Examination $exam): RedirectResponse
    {
        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'full_marks' => ['required', 'numeric', 'min:1', 'max:999'],
            'pass_marks' => ['required', 'numeric', 'min:0', 'lte:full_marks'],
            'exam_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'components' => ['nullable', 'array'],
            'components.*.name' => ['nullable', 'string', 'max:80'],
            'components.*.full_marks' => ['nullable', 'numeric', 'min:0.01', 'max:999'],
            'components.*.pass_marks' => ['nullable', 'numeric', 'min:0', 'max:999'],
        ]);

        $components = collect($data['components'] ?? [])
            ->map(fn (array $component) => [
                'name' => trim((string) ($component['name'] ?? '')),
                'full_marks' => round((float) ($component['full_marks'] ?? 0), 2),
                'pass_marks' => round((float) ($component['pass_marks'] ?? 0), 2),
            ])
            ->filter(fn (array $component) => $component['name'] !== '' || $component['full_marks'] > 0)
            ->values();

        if ($components->isNotEmpty()) {
            $invalid = $components->contains(fn (array $component) => $component['name'] === ''
                || $component['full_marks'] <= 0
                || $component['pass_marks'] > $component['full_marks']);

            if ($invalid) {
                throw ValidationException::withMessages([
                    'components' => 'Every component needs a name, full marks and a pass mark no higher than its full marks.',
                ]);
            }

            $componentTotal = round((float) $components->sum('full_marks'), 2);
            if (abs($componentTotal - (float) $data['full_marks']) > 0.01) {
                throw ValidationException::withMessages([
                    'components' => "Component full marks must add up to the paper's full marks ({$data['full_marks']}).",
                ]);
            }
        }

        $data['components'] = $components->isEmpty() ? null : $components->all();

        $exists = $exam->subjects()
            ->where('class_id', $data['class_id'])
            ->where('subject_id', $data['subject_id'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['subject_id' => 'This subject is already part of the exam for that class.']);
        }

        $subject = $exam->subjects()->create($data);

        $this->audit->record(
            'exam_subject_added',
            'Academics',
            "Subject {$subject->subject?->name} added to {$exam->name}",
            $exam,
            null,
            $data
        );

        return back()->with('success', 'Exam subject and schedule added.');
    }

    public function destroySubject(Examination $exam, ExaminationSubject $examSubject): RedirectResponse
    {
        abort_unless($examSubject->examination_id === $exam->id, 404);

        if ($examSubject->marks()->exists()) {
            return back()->with('error', 'Marks already exist for this exam subject. Remove cannot proceed because results must be preserved.');
        }

        $old = $examSubject->getAttributes();
        $examSubject->delete();

        $this->audit->record(
            'exam_subject_removed',
            'Academics',
            "Exam subject removed from {$exam->name}",
            $exam,
            $old,
            null
        );

        return back()->with('success', 'Exam subject removed.');
    }
}
