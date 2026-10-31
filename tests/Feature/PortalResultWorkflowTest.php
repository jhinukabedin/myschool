<?php

namespace Tests\Feature;

use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\GradeRule;
use App\Models\GradingScheme;
use App\Models\Mark;
use App\Models\ParentStudentLink;
use App\Models\ResultCorrection;
use App\Models\Role;
use App\Models\StudentPortalLink;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamResultService;
use App\Services\PortalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchoolData;
use Tests\TestCase;

class PortalResultWorkflowTest extends TestCase
{
    use BuildsSchoolData;
    use RefreshDatabase;

    public function test_portal_role_boundaries_and_explicit_child_links_are_enforced(): void
    {
        $this->get(route('student.dashboard'))->assertRedirect(route('student.login'));
        $this->get(route('parent.dashboard'))->assertRedirect(route('parent.login'));

        $session = $this->seedCore();
        [$first, , $class] = $this->createStudent($session, 'First');
        [$second] = $this->createStudent($session, 'Second');
        [$unlinked] = $this->createStudent($session, 'Unlinked');

        $studentUser = $this->portalUser('student', 'student.portal@example.test');
        StudentPortalLink::create([
            'user_id' => $studentUser->id,
            'student_id' => $first->id,
            'verified_at' => now(),
            'is_active' => true,
        ]);

        $parentUser = $this->portalUser('parent', 'parent.portal@example.test');

        foreach ([$first, $second] as $child) {
            ParentStudentLink::create([
                'user_id' => $parentUser->id,
                'student_id' => $child->id,
                'relationship' => 'Guardian',
                'verified_at' => now(),
                'is_active' => true,
            ]);
        }

        $this->actingAs($studentUser)->get(route('student.dashboard'))->assertOk();
        $this->actingAs($studentUser)->get(route('student.results'))->assertOk();
        $this->actingAs($studentUser)->get(route('parent.dashboard'))->assertForbidden();
        $this->actingAs($studentUser)->get(route('admin.index'))->assertForbidden();

        $this->actingAs($parentUser)->get(route('parent.dashboard'))->assertOk();
        $this->actingAs($parentUser)->get(route('parent.results.index', $first))->assertOk();
        $this->actingAs($parentUser)->get(route('parent.results.index', $second))->assertOk();
        $this->actingAs($parentUser)->get(route('parent.results.index', $unlinked))->assertForbidden();
        $this->actingAs($parentUser)->get(route('student.dashboard'))->assertForbidden();

        $orphan = $this->portalUser('student', 'orphan.portal@example.test');
        $this->actingAs($orphan)->get(route('student.dashboard'))->assertForbidden();

        $inactive = $this->portalUser('parent', 'inactive.parent@example.test', false);
        ParentStudentLink::create([
            'user_id' => $inactive->id,
            'student_id' => $first->id,
            'verified_at' => now(),
            'is_active' => true,
        ]);
        $this->actingAs($inactive)->get(route('parent.dashboard'))->assertForbidden();
    }

    public function test_portal_login_rejects_incompatible_or_inactive_accounts(): void
    {
        $session = $this->seedCore();
        [$student] = $this->createStudent($session, 'Login');
        $studentUser = $this->portalUser('student', 'login.student@example.test', true, 'Secret@12345');

        StudentPortalLink::create([
            'user_id' => $studentUser->id,
            'student_id' => $student->id,
            'verified_at' => now(),
            'is_active' => true,
        ]);

        $this->post(route('student.login.store'), [
            'email' => $studentUser->email,
            'password' => 'Secret@12345',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($studentUser);

        $this->post(route('logout'));

        $this->post(route('parent.login.store'), [
            'email' => $studentUser->email,
            'password' => 'Secret@12345',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $inactive = $this->portalUser('student', 'inactive.student@example.test', false, 'Secret@12345');

        $this->post(route('student.login.store'), [
            'email' => $inactive->email,
            'password' => 'Secret@12345',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_results_are_published_as_immutable_snapshots_and_corrections_are_audited(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();
        [$student, , $class, $section] = $this->createStudent($session, 'Result');
        [$withheldStudent] = $this->createStudent($session, 'Withheld');
        $subjects = Subject::query()->limit(2)->get();

        $scheme = GradingScheme::query()->where('name', 'SSC Standard 2026')->firstOrFail();

        $exam = Examination::create([
            'academic_session_id' => $session->id,
            'name' => 'SSC Model Test 2026',
            'exam_type' => 'ssc',
            'grading_scheme_id' => $scheme->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-10',
            'status' => 'draft',
        ]);

        $papers = $subjects->values()->map(fn (Subject $subject, int $index) => ExaminationSubject::create([
            'examination_id' => $exam->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'full_marks' => 100,
            'pass_marks' => 33,
            'exam_date' => '2026-11-0'.($index + 2),
            'start_time' => '10:00',
            'duration_minutes' => 120,
        ]));

        $service = app(ExamResultService::class);
        $rules = $service->rulesForExam($exam);

        $firstMark = $service->enterMark($papers[0], $student, 90, false, $admin, $rules);
        $service->enterMark($papers[1], $student, 40, false, $admin, $rules);
        $service->enterMark($papers[0], $withheldStudent, null, false, $admin, $rules, 'withheld');
        $service->enterMark($papers[1], $withheldStudent, null, false, $admin, $rules, 'incomplete');

        // Draft marks must never be published.
        $this->actingAs($admin)->post(route('exams.publish', $exam))->assertSessionHas('error');
        $this->assertSame('draft', $exam->fresh()->status);
        $this->assertDatabaseCount('result_snapshots', 0);

        Mark::query()
            ->whereIn('examination_subject_id', $papers->pluck('id'))
            ->update([
                'status' => 'approved',
                'submitted_at' => now(),
                'submitted_by' => $admin->id,
                'approved_at' => now(),
                'approved_by' => $admin->id,
            ]);

        $this->actingAs($admin)->post(route('exams.publish', $exam))->assertRedirect();

        $exam->refresh();
        $this->assertSame('published', $exam->status);
        $this->assertNotNull($exam->result_published_at);
        $this->assertNotNull($exam->result_locked_at);
        $this->assertDatabaseCount('result_snapshots', 2);

        $snapshot = $exam->resultSnapshots()->where('student_id', $student->id)->firstOrFail();
        $this->assertCount(7, $snapshot->grading_rules);
        $this->assertSame(130.0, (float) $snapshot->total_marks);
        $this->assertSame('Pass', $snapshot->result_status);
        $this->assertCount(2, $snapshot->result_data['subjects']);

        $withheldSnapshot = $exam->resultSnapshots()->where('student_id', $withheldStudent->id)->firstOrFail();
        $this->assertSame('Withheld', $withheldSnapshot->result_status);
        $this->assertContains('withheld', $withheldSnapshot->result_data['special_statuses']);

        $studentUser = $this->portalUser('student', 'result.student@example.test');
        StudentPortalLink::create([
            'user_id' => $studentUser->id,
            'student_id' => $student->id,
            'verified_at' => now(),
            'is_active' => true,
        ]);

        $this->actingAs($studentUser)->get(route('student.results'))
            ->assertOk()
            ->assertSee('SSC Model Test 2026');
        $this->actingAs($studentUser)->get(route('student.results.show', $exam))->assertOk();

        $csv = $this->actingAs($admin)->get(route('exams.class-result.export', [
            'exam_id' => $exam->id,
            'class_id' => $class->id,
        ]))->assertOk();

        $this->assertStringContainsString('SSC Model Test 2026', $csv->streamedContent());
        $this->assertStringContainsString($student->admission_no, $csv->streamedContent());

        // Live mark edits cannot rewrite an already published result until an
        // authorised correction refreshes the snapshot.
        $firstMark->update(['marks_obtained' => 0]);
        $published = app(PortalService::class)->result($exam->fresh(), $student);
        $this->assertSame(130.0, (float) $published['result']['total']);

        $this->actingAs($admin)->post(route('exams.marks-store'), [
            'examination_subject_id' => $papers[0]->id,
            'section_id' => $section->id,
            'marks' => [$student->id => 50],
        ])->assertSessionHasErrors('correction_reason');

        $this->actingAs($admin)->post(route('exams.marks-store'), [
            'examination_subject_id' => $papers[0]->id,
            'section_id' => $section->id,
            'marks' => [$student->id => 50],
            'correction_reason' => 'Rechecked the answer script against the marking scheme.',
        ])->assertRedirect();

        $this->assertDatabaseHas('result_corrections', [
            'mark_id' => $firstMark->id,
            'new_marks' => 50,
            'reason' => 'Rechecked the answer script against the marking scheme.',
        ]);
        $this->assertSame('approved', $firstMark->fresh()->status);
        $this->assertSame(90.0, (float) $snapshot->fresh()->total_marks);

        $studentUser->refresh();
        $this->actingAs($studentUser)->get(route('student.results.show', $exam))
            ->assertOk()
            ->assertSee('90.00');

        // Unpublishing hides the sheet immediately. Re-publishing reuses the
        // original grading snapshot rather than the scheme's current rules.
        GradeRule::query()->where('grading_scheme_id', $scheme->id)->where('grade', 'A+')->update([
            'min_percent' => 95,
            'max_percent' => 100,
        ]);

        $this->actingAs($admin)->post(route('exams.unpublish', $exam), [
            'reason' => 'Result verification requested by the examination committee.',
        ])->assertRedirect();

        $this->assertSame('completed', $exam->fresh()->status);
        $this->actingAs($studentUser)->get(route('student.results.show', $exam))->assertNotFound();
        $this->actingAs($studentUser)->get(route('student.results'))->assertDontSee('SSC Model Test 2026');

        $this->actingAs($admin)->post(route('exams.publish', $exam))->assertRedirect();

        $snapshot->refresh();
        $this->assertSame('published', $exam->fresh()->status);
        $this->assertSame(80.0, (float) $snapshot->grading_rules[0]['min_percent']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'exam_published']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'exam_unpublished']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'marks_corrected']);
        $this->assertSame(1, ResultCorrection::query()->where('mark_id', $firstMark->id)->count());
    }

    private function portalUser(string $role, string $email, bool $active = true, string $password = 'Secret@12345'): User
    {
        $role = Role::query()->where('slug', $role)->firstOrFail();

        return User::create([
            'role_id' => $role->id,
            'name' => ucfirst($role->slug).' Portal User',
            'email' => $email,
            'password' => $password,
            'is_active' => $active,
        ]);
    }

    private function admin(): User
    {
        return User::query()->where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();
    }
}
