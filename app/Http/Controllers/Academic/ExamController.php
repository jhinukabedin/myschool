<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExamRequest;
use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\GradingScheme;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\AuditLogService;
use App\Services\ExamResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExamController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly ExamResultService $results
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
        return view('academics.exams.form', [
            'exam' => new Examination([
                'academic_session_id' => current_session()?->id,
                'status' => 'draft',
                'exam_type' => 'term',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(7)->toDateString(),
            ]),
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'gradingSchemes' => GradingScheme::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreExamRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->guardPublicationStatus($data);

        $exam = Examination::create($data);

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
        ]);
    }

    public function update(StoreExamRequest $request, Examination $exam): RedirectResponse
    {
        $data = $request->validated();
        $this->guardPublicationStatus($data, $exam);

        $before = $exam->getAttributes();
        $exam->update($data);

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
