<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\GradeRule;
use App\Models\GradingScheme;
use App\Models\Mark;
use App\Models\ResultSnapshot;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ExamResultService
{
    public function gradeFor(float $percentage, ?Collection $rules = null): mixed
    {
        $rules ??= $this->defaultRules();

        return $rules->first(fn ($rule) => $percentage >= (float) $rule->min_percent
            && $percentage <= (float) $rule->max_percent);
    }

    /**
     * Rules explicitly chosen by the examination, then the default scheme for
     * this exam type/session, then the legacy global rules.
     *
     * @return Collection<int, GradeRule>
     */
    public function rulesForExam(Examination $exam): Collection
    {
        $exam->loadMissing('gradingScheme');

        if ($exam->gradingScheme?->rules()->exists()) {
            return $exam->gradingScheme->rules()->ordered()->get();
        }

        $scheme = GradingScheme::query()
            ->active()
            ->where(fn ($query) => $query->whereNull('academic_session_id')
                ->orWhere('academic_session_id', $exam->academic_session_id))
            ->where(fn ($query) => $query->whereNull('exam_type')
                ->orWhere('exam_type', $exam->exam_type))
            ->orderByDesc('exam_type')
            ->orderByDesc('is_default')
            ->first();

        if ($scheme?->rules()->exists()) {
            return $scheme->rules()->ordered()->get();
        }

        return $this->defaultRules();
    }

    public function defaultRules(): Collection
    {
        return GradeRule::query()->whereNull('grading_scheme_id')->ordered()->get();
    }

    public function enterMark(
        ExaminationSubject $examSubject,
        Student $student,
        ?float $marks,
        bool $absent,
        User $actor,
        ?Collection $rules = null,
        ?string $specialStatus = null,
        ?string $specialNote = null,
        ?array $componentMarks = null
    ): Mark {
        $specialStatus = $specialStatus !== '' ? $specialStatus : null;
        $validStatuses = ['absent', 'exempt', 'withheld', 'incomplete', 'disqualified'];

        if ($specialStatus !== null && ! in_array($specialStatus, $validStatuses, true)) {
            throw ValidationException::withMessages(['special_status' => 'Unknown result status.']);
        }

        $absent = $absent || $specialStatus === 'absent';

        if (! $absent && $specialStatus !== 'exempt' && $specialStatus !== 'withheld' && $specialStatus !== 'incomplete' && $specialStatus !== 'disqualified') {
            if ($marks === null) {
                throw ValidationException::withMessages(['marks' => 'Enter marks or mark the student absent.']);
            }

            if ($marks < 0 || $marks > (float) $examSubject->full_marks) {
                throw ValidationException::withMessages([
                    'marks' => "Marks must be between 0 and {$examSubject->full_marks}.",
                ]);
            }

            $percentage = ((float) $marks / (float) $examSubject->full_marks) * 100;
        } else {
            $percentage = 0;
        }

        $rule = $this->gradeFor($percentage, $rules ?? $this->rulesForExam($examSubject->examination));
        $componentPassFailed = $this->componentPassFailed($examSubject, $componentMarks);
        $blocked = in_array($specialStatus, ['withheld', 'incomplete', 'disqualified'], true);
        $exempt = $specialStatus === 'exempt';

        $mark = Mark::firstOrNew([
            'examination_subject_id' => $examSubject->id,
            'student_id' => $student->id,
        ]);

        $mark->fill([
            'marks_obtained' => $absent || $exempt ? null : $marks,
            'component_marks' => $absent || $exempt ? null : $componentMarks,
            'is_absent' => $absent,
            'grade' => match (true) {
                $absent => 'F',
                $exempt => 'Exempt',
                $blocked => strtoupper((string) $specialStatus),
                default => $componentPassFailed ? 'F' : $rule?->grade,
            },
            'grade_point' => match (true) {
                $exempt => null,
                $absent, $blocked => 0,
                $componentPassFailed => 0,
                default => $rule?->grade_point,
            },
            'entered_by' => $mark->entered_by ?? $actor->id,
            'updated_by' => $actor->id,
            'special_status' => $specialStatus,
            'special_note' => $specialNote ?: null,
        ]);

        if (! $mark->exists) {
            $mark->status = 'draft';
        } elseif (in_array($mark->status, ['submitted', 'approved'], true)) {
            // A change to submitted/approved marks restarts the workflow so it
            // must be submitted (and approved, if enabled) again.
            $mark->status = 'draft';
            $mark->submitted_at = null;
            $mark->submitted_by = null;
            $mark->approved_at = null;
            $mark->approved_by = null;
        }

        $mark->save();

        return $mark;
    }

    /**
     * Calculate a student's result for one exam and class.
     *
     * @return array<string, mixed>
     */
    public function studentResult(
        Examination $exam,
        Student $student,
        SchoolClass $class,
        ?Collection $rules = null,
        ?Collection $examSubjects = null,
        ?Collection $marks = null
    ): array {
        $rules ??= $this->rulesForExam($exam);
        $examSubjects ??= $exam->subjects->where('class_id', $class->id)->values();
        $marks ??= Mark::query()
            ->where('student_id', $student->id)
            ->whereIn('examination_subject_id', $examSubjects->pluck('id'))
            ->get()
            ->keyBy('examination_subject_id');

        $subjects = [];
        $total = 0.0;
        $fullTotal = 0.0;
        $points = 0.0;
        $failed = 0;
        $missing = 0;
        $counted = 0;
        $specialStatuses = [];

        foreach ($examSubjects as $examSubject) {
            $mark = $marks->get($examSubject->id);
            $special = $mark?->special_status;
            $isAbsent = (bool) ($mark?->is_absent) || $special === 'absent';
            $isExempt = $special === 'exempt';

            if ($isExempt) {
                $subjects[] = [
                    'exam_subject' => $examSubject,
                    'subject' => $examSubject->subject,
                    'mark' => $mark,
                    'obtained' => null,
                    'full_marks' => (float) $examSubject->full_marks,
                    'pass_marks' => (float) $examSubject->pass_marks,
                    'grade' => 'Exempt',
                    'grade_point' => null,
                    'absent' => false,
                    'exempt' => true,
                    'special_status' => 'exempt',
                    'passed' => true,
                    'components' => $this->componentBreakdown($examSubject, $mark),
                ];

                continue;
            }

            if (! $mark) {
                $missing++;
                $specialStatuses[] = 'incomplete';
                $counted++;
                $fullTotal += (float) $examSubject->full_marks;

                $subjects[] = [
                    'exam_subject' => $examSubject,
                    'subject' => $examSubject->subject,
                    'mark' => null,
                    'obtained' => null,
                    'full_marks' => (float) $examSubject->full_marks,
                    'pass_marks' => (float) $examSubject->pass_marks,
                    'grade' => '—',
                    'grade_point' => null,
                    'absent' => false,
                    'exempt' => false,
                    'special_status' => 'missing',
                    'passed' => false,
                    'components' => $this->componentBreakdown($examSubject, null),
                ];

                continue;
            }

            $obtained = $isAbsent ? null : (float) $mark->marks_obtained;
            $blocked = in_array($special, ['withheld', 'incomplete', 'disqualified'], true);
            $componentPassFailed = $this->componentPassFailed($examSubject, $mark->component_marks);
            $passed = ! $isAbsent && ! $blocked && ! $componentPassFailed && $obtained >= (float) $examSubject->pass_marks;
            $point = $isAbsent || $blocked ? 0.0 : (float) $mark->grade_point;

            if (! $passed) {
                $failed++;
                $point = 0.0;
            }

            if ($special) {
                $specialStatuses[] = $special;
            }

            $subjects[] = [
                'exam_subject' => $examSubject,
                'subject' => $examSubject->subject,
                'mark' => $mark,
                'obtained' => $obtained,
                'full_marks' => (float) $examSubject->full_marks,
                'pass_marks' => (float) $examSubject->pass_marks,
                'grade' => $special ? strtoupper($special) : ($componentPassFailed ? 'F' : ($mark->grade ?? '—')),
                'grade_point' => $point,
                'absent' => $isAbsent,
                'exempt' => false,
                'special_status' => $special,
                'passed' => $passed,
                'components' => $this->componentBreakdown($examSubject, $mark),
            ];

            $total += $obtained ?? 0.0;
            $fullTotal += (float) $examSubject->full_marks;
            $points += $point;
            $counted++;
        }

        $subjectCount = max(1, $counted);
        $percentage = $fullTotal > 0 ? ($total / $fullTotal) * 100 : 0;
        $gpa = ($failed > 0 || $missing > 0) ? 0.0 : round($points / $subjectCount, 2);
        $overallRule = $this->gradeFor($percentage, $rules);

        $resultStatus = match (true) {
            in_array('disqualified', $specialStatuses, true) => 'Disqualified',
            in_array('withheld', $specialStatuses, true) => 'Withheld',
            $missing > 0, in_array('incomplete', $specialStatuses, true) => 'Incomplete',
            $failed > 0 => 'Fail',
            $counted === 0 => 'Exempt',
            default => 'Pass',
        };

        return [
            'student' => $student,
            'subjects' => $subjects,
            'total' => round($total, 2),
            'full_total' => round($fullTotal, 2),
            'percentage' => round($percentage, 2),
            'gpa' => $gpa,
            'grade' => in_array($resultStatus, ['Pass'], true) ? ($overallRule?->grade ?? '—') : ($resultStatus === 'Fail' ? 'F' : '—'),
            'failed_subjects' => $failed,
            'missing_subjects' => $missing,
            'special_statuses' => array_values(array_unique($specialStatuses)),
            'result' => $resultStatus,
        ];
    }

    /**
     * Produce a ranked merit list for a class (optionally filtered by section).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function meritList(
        Examination $exam,
        SchoolClass $class,
        ?Section $section = null,
        ?Collection $rules = null
    ): Collection {
        $rules ??= $this->rulesForExam($exam);
        $examSubjects = $exam->subjects->where('class_id', $class->id)->values();

        $enrollments = Enrollment::query()
            ->where('academic_session_id', $exam->academic_session_id)
            ->where('class_id', $class->id)
            ->when($section, fn ($q) => $q->where('section_id', $section->id))
            ->where('status', 'active')
            ->with('student')
            ->get();

        $studentIds = $enrollments->pluck('student_id');
        $allMarks = Mark::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('examination_subject_id', $examSubjects->pluck('id'))
            ->get()
            ->groupBy('student_id');

        $results = $enrollments
            ->map(function (Enrollment $enrollment) use ($exam, $class, $rules, $examSubjects, $allMarks) {
                $result = $this->studentResult(
                    $exam,
                    $enrollment->student,
                    $class,
                    $rules,
                    $examSubjects,
                    $allMarks->get($enrollment->student_id, collect())->keyBy('examination_subject_id')
                );
                $result['enrollment'] = $enrollment;

                return $result;
            })
            ->sortBy([
                fn ($a, $b) => $b['gpa'] <=> $a['gpa'],
                fn ($a, $b) => $b['total'] <=> $a['total'],
                fn ($a, $b) => strcmp($a['student']->full_name, $b['student']->full_name),
            ])
            ->values()
            ->map(function ($result, $index) {
                $result['rank'] = $index + 1;

                return $result;
            });

        return $results;
    }

    /**
     * @return Collection<int, GradeRule>
     */
    public function rulesFromSnapshotPayload(array $rules): Collection
    {
        return collect($rules)
            ->map(fn ($rule) => new GradeRule((array) $rule))
            ->sortByDesc(fn (GradeRule $rule) => (float) $rule->min_percent)
            ->values();
    }

    /**
     * Published results must always keep the boundaries that were in force when
     * they were first released. Re-publishing after a correction reuses the
     * stored snapshot rules instead of the scheme's current rules.
     *
     * @return Collection<int, GradeRule>
     */
    public function rulesForPublishedExam(Examination $exam): Collection
    {
        $snapshot = $exam->resultSnapshots()->oldest('id')->first();

        if ($snapshot && ! empty($snapshot->grading_rules)) {
            return $this->rulesFromSnapshotPayload($snapshot->grading_rules);
        }

        return $this->rulesForExam($exam);
    }

    /**
     * @return array<int, array{name: string, full_marks: float, obtained: float|null}>
     */
    private function componentBreakdown(ExaminationSubject $examSubject, ?Mark $mark): array
    {
        $componentMarks = (array) ($mark?->component_marks ?? []);

        return collect($examSubject->components ?? [])
            ->map(function (array $component, int $index) use ($componentMarks) {
                $obtained = array_key_exists($index, $componentMarks) && $componentMarks[$index] !== null
                    ? (float) $componentMarks[$index]
                    : null;

                return [
                    'name' => $component['name'] ?? 'Component',
                    'full_marks' => (float) ($component['full_marks'] ?? 0),
                    'pass_marks' => (float) ($component['pass_marks'] ?? 0),
                    'requires_pass' => (bool) ($component['requires_pass'] ?? false),
                    'obtained' => $obtained,
                ];
            })
            ->values()
            ->all();
    }

    private function componentPassFailed(ExaminationSubject $examSubject, ?array $componentMarks): bool
    {
        foreach ($examSubject->components ?? [] as $index => $component) {
            if (! ($component['requires_pass'] ?? false)) {
                continue;
            }

            $obtained = $componentMarks[$index] ?? null;
            if ($obtained === null || (float) $obtained < (float) ($component['pass_marks'] ?? 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a serialisable result payload for a published snapshot. Eloquent
     * models are intentionally reduced to scalar fields so the snapshot remains
     * readable even after a student, subject or class record changes.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function resultDataForSnapshot(array $result, ?Enrollment $enrollment = null): array
    {
        return [
            'student_id' => $result['student']?->id,
            'student_name' => $result['student']?->full_name,
            'student_code' => $result['student']?->student_code,
            'admission_no' => $result['student']?->admission_no,
            'enrollment_id' => $enrollment?->id,
            'class_id' => $enrollment?->class_id,
            'class_name' => $enrollment?->class?->name,
            'section_name' => $enrollment?->section?->name,
            'roll_no' => $enrollment?->roll_no,
            'subjects' => collect($result['subjects'])->map(fn (array $row) => [
                'subject_id' => $row['exam_subject']?->subject_id,
                'subject_name' => $row['subject']?->name,
                'full_marks' => (float) $row['full_marks'],
                'pass_marks' => (float) $row['pass_marks'],
                'obtained' => $row['obtained'] === null ? null : (float) $row['obtained'],
                'grade' => $row['grade'],
                'grade_point' => $row['grade_point'] === null ? null : (float) $row['grade_point'],
                'absent' => (bool) $row['absent'],
                'exempt' => (bool) $row['exempt'],
                'special_status' => $row['special_status'],
                'passed' => (bool) $row['passed'],
                'components' => $row['components'] ?? [],
            ])->values()->all(),
            'total' => (float) $result['total'],
            'full_total' => (float) $result['full_total'],
            'percentage' => (float) $result['percentage'],
            'gpa' => (float) $result['gpa'],
            'grade' => $result['grade'],
            'failed_subjects' => (int) $result['failed_subjects'],
            'missing_subjects' => (int) $result['missing_subjects'],
            'special_statuses' => array_values($result['special_statuses']),
            'result' => $result['result'],
        ];
    }

    /**
     * Rehydrate a stored snapshot into the same shape as studentResult(). This
     * is what portals read, so later mark edits cannot silently rewrite a
     * published result sheet.
     *
     * @return array<string, mixed>
     */
    public function resultFromSnapshot(ResultSnapshot $snapshot): array
    {
        $data = $snapshot->result_data ?? [];
        $subjects = collect($data['subjects'] ?? [])->map(function ($row) {
            $row = (array) $row;

            return [
                'exam_subject' => null,
                'subject' => (object) ['name' => $row['subject_name'] ?? 'Subject'],
                'mark' => null,
                'obtained' => $row['obtained'] ?? null,
                'full_marks' => (float) ($row['full_marks'] ?? 0),
                'pass_marks' => (float) ($row['pass_marks'] ?? 0),
                'grade' => $row['grade'] ?? '—',
                'grade_point' => $row['grade_point'] ?? null,
                'absent' => (bool) ($row['absent'] ?? false),
                'exempt' => (bool) ($row['exempt'] ?? false),
                'special_status' => $row['special_status'] ?? null,
                'passed' => (bool) ($row['passed'] ?? false),
                'components' => $row['components'] ?? [],
            ];
        })->values()->all();

        return [
            'student' => $snapshot->student,
            'subjects' => $subjects,
            'total' => (float) ($data['total'] ?? $snapshot->total_marks),
            'full_total' => (float) ($data['full_total'] ?? 0),
            'percentage' => (float) ($data['percentage'] ?? 0),
            'gpa' => (float) ($data['gpa'] ?? $snapshot->gpa),
            'grade' => $data['grade'] ?? $snapshot->grade,
            'failed_subjects' => (int) ($data['failed_subjects'] ?? 0),
            'missing_subjects' => (int) ($data['missing_subjects'] ?? 0),
            'special_statuses' => $data['special_statuses'] ?? [],
            'result' => $data['result'] ?? $snapshot->result_status,
        ];
    }

    /**
     * Generate or refresh immutable snapshots for every active enrollment in
     * the classes covered by the examination. Callers are responsible for
     * checking that marks are finalized.
     */
    public function publishSnapshots(Examination $exam, User $actor, ?Collection $rules = null): int
    {
        $exam->loadMissing(['subjects.subject', 'subjects.class']);
        $rules ??= $this->rulesForPublishedExam($exam);

        if ($rules->isEmpty()) {
            throw ValidationException::withMessages([
                'grading' => 'No grade boundaries are configured. Add a grading scheme before publishing results.',
            ]);
        }

        $classIds = $exam->subjects->pluck('class_id')->unique()->values();
        $enrollments = Enrollment::query()
            ->with(['student', 'class', 'section'])
            ->where('academic_session_id', $exam->academic_session_id)
            ->whereIn('class_id', $classIds)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->get()
            ->unique('student_id');

        $published = 0;
        $existingSnapshots = ResultSnapshot::query()
            ->where('examination_id', $exam->id)
            ->get()
            ->keyBy('student_id');

        $gradingSnapshot = $rules->map(fn (GradeRule $rule) => [
            'name' => $rule->name,
            'min_percent' => (float) $rule->min_percent,
            'max_percent' => (float) $rule->max_percent,
            'grade' => $rule->grade,
            'grade_point' => (float) $rule->grade_point,
            'is_fail' => (bool) $rule->is_fail,
        ])->values()->all();

        foreach ($enrollments as $enrollment) {
            $examSubjects = $exam->subjects->where('class_id', $enrollment->class_id)->values();
            $result = $this->studentResult(
                $exam,
                $enrollment->student,
                $enrollment->class,
                $rules,
                $examSubjects
            );

            ResultSnapshot::updateOrCreate(
                [
                    'examination_id' => $exam->id,
                    'student_id' => $enrollment->student_id,
                ],
                [
                    'grading_scheme_id' => $exam->grading_scheme_id,
                    'grading_rules' => $gradingSnapshot,
                    'result_data' => $this->resultDataForSnapshot($result, $enrollment),
                    'total_marks' => $result['total'],
                    'gpa' => $result['gpa'],
                    'grade' => $result['grade'],
                    'result_status' => $result['result'],
                    'published_at' => $existingSnapshots->get($enrollment->student_id)?->published_at ?? now(),
                    'generated_by' => $actor->id,
                ]
            );

            $published++;
        }

        return $published;
    }

    public function sessionExamOptions(?AcademicSession $session)
    {
        return Examination::query()
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->orderByDesc('start_date')
            ->get();
    }
}
