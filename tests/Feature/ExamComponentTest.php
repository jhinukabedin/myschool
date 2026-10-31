<?php

namespace Tests\Feature;

use App\Models\Examination;
use App\Models\Mark;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchoolData;
use Tests\TestCase;

class ExamComponentTest extends TestCase
{
    use BuildsSchoolData;
    use RefreshDatabase;

    public function test_component_papers_are_configured_marked_totaled_and_snapshotted(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();
        [$student, , $class, $section] = $this->createStudent($session, 'Component');
        $subjects = Subject::query()->limit(2)->get();

        $exam = Examination::create([
            'academic_session_id' => $session->id,
            'name' => 'Component Examination 2026',
            'exam_type' => 'term',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)->post(route('exams.subjects.store', $exam), [
            'class_id' => $class->id,
            'subject_id' => $subjects[0]->id,
            'full_marks' => 100,
            'pass_marks' => 33,
            'components' => [
                ['name' => 'Theory', 'full_marks' => 70, 'pass_marks' => 25],
                ['name' => 'Practical', 'full_marks' => 30, 'pass_marks' => 10],
            ],
        ])->assertRedirect();

        $paper = $exam->subjects()->firstOrFail();
        $this->assertCount(2, $paper->components);
        $this->assertSame('Theory', $paper->components[0]['name']);

        $this->actingAs($admin)->get(route('exams.show', $exam))
            ->assertOk()
            ->assertSee('Theory 70 + Practical 30');

        // Components must account for the whole paper.
        $this->actingAs($admin)->post(route('exams.subjects.store', $exam), [
            'class_id' => $class->id,
            'subject_id' => $subjects[1]->id,
            'full_marks' => 100,
            'pass_marks' => 33,
            'components' => [
                ['name' => 'Theory', 'full_marks' => 60, 'pass_marks' => 20],
                ['name' => 'Practical', 'full_marks' => 30, 'pass_marks' => 10],
            ],
        ])->assertSessionHasErrors('components');

        $this->actingAs($admin)->post(route('exams.marks-store'), [
            'examination_subject_id' => $paper->id,
            'section_id' => $section->id,
            'components' => [$student->id => [55, 25]],
        ])->assertRedirect();

        $mark = Mark::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('80.00', $mark->marks_obtained);
        $this->assertSame(55.0, (float) $mark->component_marks[0]);
        $this->assertSame(25.0, (float) $mark->component_marks[1]);

        $this->actingAs($admin)->get(route('exams.marks-entry', [
            'exam_id' => $exam->id,
            'examination_subject_id' => $paper->id,
            'section_id' => $section->id,
        ]))->assertOk()
            ->assertSee('Practical')
            ->assertSee('name="components['.$student->id.'][0]"', false);

        // A component over its own maximum is rejected.
        $this->actingAs($admin)->post(route('exams.marks-store'), [
            'examination_subject_id' => $paper->id,
            'section_id' => $section->id,
            'components' => [$student->id => [80, 20]],
        ])->assertSessionHasErrors('components');

        $this->assertSame('80.00', $mark->fresh()->marks_obtained);

        // A valid correction changes both the breakdown and the total.
        $this->actingAs($admin)->post(route('exams.marks-store'), [
            'examination_subject_id' => $paper->id,
            'section_id' => $section->id,
            'components' => [$student->id => [60, 30]],
        ])->assertRedirect();

        $mark->refresh();
        $this->assertSame('90.00', $mark->marks_obtained);
        $this->assertSame(60.0, (float) $mark->component_marks[0]);

        $this->actingAs($admin)->post(route('exams.marks-submit'), [
            'examination_subject_id' => $paper->id,
            'section_id' => $section->id,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('exams.marks-approve'), [
            'examination_subject_id' => $paper->id,
            'section_id' => $section->id,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('exams.publish', $exam))->assertRedirect();

        $snapshot = $exam->fresh()->resultSnapshots()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame(90.0, (float) $snapshot->total_marks);
        $this->assertSame('Theory', $snapshot->result_data['subjects'][0]['components'][0]['name']);
        $this->assertSame(60.0, (float) $snapshot->result_data['subjects'][0]['components'][0]['obtained']);

        $this->actingAs($admin)->get(route('exams.result-sheet', [
            'exam' => $exam,
            'student' => $student,
        ]))->assertOk()
            ->assertSee('Theory: 60.00/70');
    }

    private function admin(): User
    {
        return User::query()->where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();
    }
}
