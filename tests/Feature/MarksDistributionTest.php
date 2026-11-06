<?php

namespace Tests\Feature;

use App\Models\ClassSubjectMarkOverride;
use App\Models\Examination;
use App\Models\Mark;
use App\Models\ExaminationSubject;
use App\Models\Subject;
use App\Models\SubjectMarkDefault;
use App\Models\User;
use App\Services\MarksDistributionService;
use App\Services\ExamResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchoolData;
use Tests\TestCase;

class MarksDistributionTest extends TestCase
{
    use BuildsSchoolData;
    use RefreshDatabase;

    public function test_class_override_falls_back_to_global_default_and_subject_default(): void
    {
        $this->seedCore();
        $subject = Subject::query()->where('code', 'BAN')->firstOrFail();
        $class4 = \App\Models\SchoolClass::where('numeric_level', 6)->firstOrFail();
        $class5 = \App\Models\SchoolClass::where('numeric_level', 7)->firstOrFail();

        $global = SubjectMarkDefault::create([
            'subject_id' => $subject->id,
            'full_marks' => 100,
            'pass_marks' => 33,
            'components' => [['name' => 'Written', 'full_marks' => 100, 'pass_marks' => 0, 'requires_pass' => false]],
        ]);
        ClassSubjectMarkOverride::create([
            'class_id' => $class4->id,
            'subject_id' => $subject->id,
            'full_marks' => 100,
            'pass_marks' => 33,
            'components' => [
                ['name' => 'Written', 'full_marks' => 50, 'pass_marks' => 0, 'requires_pass' => false],
                ['name' => 'MCQ', 'full_marks' => 50, 'pass_marks' => 0, 'requires_pass' => false],
            ],
        ]);

        $service = app(MarksDistributionService::class);
        $this->assertSame('Class override', $service->resolve($class4->id, $subject)['source']);
        $this->assertSame(2, count($service->resolve($class4->id, $subject)['components']));
        $this->assertSame('Global default', $service->resolve($class5->id, $subject)['source']);
        $this->assertSame('Written', $service->resolve($class5->id, $subject)['components'][0]['name']);
        $subject->update(['full_marks' => 80, 'pass_marks' => 26]);
        SubjectMarkDefault::where('subject_id', $subject->id)->delete();
        $this->assertSame('Subject default', $service->resolve($class5->id, $subject->fresh())['source']);
    }

    public function test_exam_saves_resolved_distribution_snapshot_and_requires_confirmation_to_change_marked_paper(): void
    {
        $session = $this->seedCore();
        $admin = User::where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();
        [, , $class] = $this->createStudent($session, 'Distribution');
        $subject = Subject::where('code', 'BAN')->firstOrFail();
        $this->actingAs($admin)->get(route('exams.distributions'))->assertOk()->assertSee('Global defaults');
        $this->get(route('exams.create'))->assertOk()->assertSee('General Information')->assertSee('Subject &amp; Marks', false)->assertSee('Exam Schedule')->assertSee('Settings');
        SubjectMarkDefault::create([
            'subject_id' => $subject->id,
            'full_marks' => 100,
            'pass_marks' => 33,
            'components' => [['name' => 'Written', 'full_marks' => 100, 'pass_marks' => 0, 'requires_pass' => false]],
        ]);

        $payload = [
            'academic_session_id' => $session->id,
            'name' => 'Distribution Snapshot Exam',
            'exam_type' => 'term',
            'grading_scheme_id' => null,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'status' => 'draft',
            'selected_classes' => [$class->id],
            'distribution' => [$class->id => [$subject->id => [
                'full_marks' => 100,
                'pass_marks' => 33,
                'components' => [
                    ['name' => 'Written', 'full_marks' => 50, 'pass_marks' => 20, 'requires_pass' => 1],
                    ['name' => 'MCQ', 'full_marks' => 50, 'pass_marks' => 15],
                ],
                'exam_date' => '2026-12-02',
                'start_time' => '09:00',
                'duration_minutes' => 90,
            ]]],
        ];

        $response = $this->actingAs($admin)->post(route('exams.store'), $payload)->assertRedirect();
        $exam = Examination::where('name', $payload['name'])->firstOrFail();
        $paper = $exam->subjects()->firstOrFail();
        $this->assertSame(2, count($paper->components));
        $this->assertTrue($paper->components[0]['requires_pass']);
        $this->assertSame('09:00', substr((string) $paper->start_time, 0, 5));

        Mark::create([
            'examination_subject_id' => $paper->id,
            'student_id' => \App\Models\Enrollment::where('academic_session_id', $session->id)->where('class_id', $class->id)->value('student_id'),
            'marks_obtained' => 70,
            'component_marks' => [35, 35],
            'is_absent' => false,
            'entered_by' => $admin->id,
        ]);

        $updated = $payload;
        $updated['name'] = $exam->name;
        $updated['distribution'][$class->id][$subject->id]['components'][0]['full_marks'] = 60;
        $updated['distribution'][$class->id][$subject->id]['components'][1]['full_marks'] = 40;
        $this->put(route('exams.update', $exam), $updated)->assertSessionHasErrors('confirm_distribution_change');
        $this->assertSame(50.0, (float) $paper->fresh()->components[0]['full_marks']);

        $updated['confirm_distribution_change'] = 1;
        $this->put(route('exams.update', $exam), $updated)->assertRedirect(route('exams.show', $exam));
        $this->assertSame(60.0, (float) $paper->fresh()->components[0]['full_marks']);
    }

    public function test_invalid_component_sum_and_duplicate_names_are_rejected(): void
    {
        $service = app(MarksDistributionService::class);
        try {
            $service->normalize(['full_marks' => 100, 'pass_marks' => 33, 'components' => [
                ['name' => 'Written', 'full_marks' => 50],
            ]]);
            $this->fail('A partial component sum must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('distribution', $exception->errors());
        }

        try {
            $service->normalize(['full_marks' => 100, 'pass_marks' => 33, 'components' => [
                ['name' => 'Written', 'full_marks' => 50],
                ['name' => 'written', 'full_marks' => 50],
            ]]);
            $this->fail('Duplicate component names must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('distribution', $exception->errors());
        }
    }

    public function test_component_pass_rule_fails_subject_even_when_overall_pass_mark_is_met(): void
    {
        $session = $this->seedCore();
        $admin = User::where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();
        [$student, , $class] = $this->createStudent($session, 'ComponentRule');
        $subject = Subject::where('code', 'SCI')->firstOrFail();
        $exam = Examination::create([
            'academic_session_id' => $session->id,
            'name' => 'Component Pass Rule Exam',
            'exam_type' => 'term',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'status' => 'draft',
        ]);
        $paper = ExaminationSubject::create([
            'examination_id' => $exam->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'full_marks' => 100,
            'pass_marks' => 33,
            'components' => [
                ['name' => 'Written', 'full_marks' => 50, 'pass_marks' => 20, 'requires_pass' => true],
                ['name' => 'Practical', 'full_marks' => 50, 'pass_marks' => 15, 'requires_pass' => false],
            ],
        ]);

        $results = app(ExamResultService::class);
        $mark = $results->enterMark($paper, $student, 75, false, $admin, null, null, null, [10, 65]);
        $this->assertSame('F', $mark->grade);
        $this->assertSame('0.00', $mark->grade_point);
        $result = $results->studentResult($exam->fresh(['subjects.subject', 'subjects.class']), $student, $class);
        $this->assertFalse($result['subjects'][0]['passed']);
        $this->assertSame('Fail', $result['result']);
    }
}
