<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\Mark;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\BuildsSchoolData;
use Tests\TestCase;

class TeacherAssignmentMarksTest extends TestCase
{
    use BuildsSchoolData;
    use RefreshDatabase;

    public function test_assignments_detect_duplicates_and_conflicts_and_are_deactivated_not_deleted(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();
        [$student, $enrollment, $class, $section] = $this->createStudent($session);
        [$teacherA, $staffA] = $this->teacher('teacher.a@example.com');
        [$teacherB, $staffB] = $this->teacher('teacher.b@example.com');
        $subject = Subject::query()->firstOrFail();

        $payload = [
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'staff_id' => $staffA->id,
            'starts_on' => now()->subDay()->toDateString(),
            'is_active' => 1,
        ];

        $this->actingAs($admin)->post(route('assignments.store'), $payload)->assertRedirect(route('assignments.index'));

        $assignment = TeacherAssignment::query()->where('staff_id', $staffA->id)->firstOrFail();
        $this->assertTrue($assignment->is_active);

        // The same teacher cannot have two identical active assignments.
        $this->actingAs($admin)->post(route('assignments.store'), $payload)->assertSessionHasErrors('staff_id');

        // A different teacher on the same paper conflicts while co-teaching is off.
        $this->actingAs($admin)->post(route('assignments.store'), array_merge($payload, ['staff_id' => $staffB->id]))
            ->assertSessionHasErrors('section_id');

        // Co-teaching can be enabled explicitly in settings.
        SchoolSetting::updateOrCreate(['key' => 'allow_co_teaching'], ['value' => '1', 'group' => 'website']);
        Cache::forget('school_settings');

        $this->actingAs($admin)->post(route('assignments.store'), array_merge($payload, ['staff_id' => $staffB->id]))
            ->assertRedirect(route('assignments.index'));

        $this->assertSame(2, TeacherAssignment::query()->where('academic_session_id', $session->id)->count());

        // Deactivation preserves the historical row.
        $this->actingAs($admin)->post(route('assignments.toggle', $assignment))->assertRedirect();
        $this->assertFalse($assignment->fresh()->is_active);
        $this->assertDatabaseHas('teacher_assignments', ['id' => $assignment->id]);
    }

    public function test_teacher_cannot_change_another_teachers_marks_even_with_tampered_ids(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();
        [$student, $enrollment, $class, $section] = $this->createStudent($session);
        [$teacherA, $staffA] = $this->teacher('teacher.a@example.com');
        [$teacherB, $staffB] = $this->teacher('teacher.b@example.com');

        $subjects = Subject::query()->limit(2)->get();
        $this->assertCount(2, $subjects);

        $exam = $this->exam($session, 'Authorization Exam');
        $paperA = $this->paper($exam, $class, $subjects[0]);
        $paperB = $this->paper($exam, $class, $subjects[1]);

        $this->assign($session, $class, $section, $subjects[0], $staffA);
        $this->assign($session, $class, $section, $subjects[1], $staffB);

        // Teacher A may enter marks for their own paper.
        $this->actingAs($teacherA)->post(route('exams.marks-store'), [
            'examination_subject_id' => $paperA->id,
            'section_id' => $section->id,
            'marks' => [$student->id => 80],
        ])->assertRedirect();

        $this->assertDatabaseHas('marks', [
            'examination_subject_id' => $paperA->id,
            'student_id' => $student->id,
            'marks_obtained' => 80,
        ]);

        // Tampered request targeting Teacher B's paper is rejected and no row is written.
        $this->actingAs($teacherA)->post(route('exams.marks-store'), [
            'examination_subject_id' => $paperB->id,
            'section_id' => $section->id,
            'marks' => [$student->id => 95],
        ])->assertForbidden();

        $this->assertDatabaseMissing('marks', [
            'examination_subject_id' => $paperB->id,
            'student_id' => $student->id,
        ]);

        // Teacher B may enter their own paper.
        $this->actingAs($teacherB)->post(route('exams.marks-store'), [
            'examination_subject_id' => $paperB->id,
            'section_id' => $section->id,
            'marks' => [$student->id => 70],
        ])->assertRedirect();

        $this->assertDatabaseHas('marks', [
            'examination_subject_id' => $paperB->id,
            'marks_obtained' => 70,
            'entered_by' => $teacherB->id,
        ]);

        // Tampering back to Teacher B's paper as Teacher A is still forbidden.
        $this->actingAs($teacherA)->post(route('exams.marks-store'), [
            'examination_subject_id' => $paperB->id,
            'section_id' => $section->id,
            'marks' => [$student->id => 100],
        ])->assertForbidden();

        $this->assertDatabaseHas('marks', [
            'examination_subject_id' => $paperB->id,
            'marks_obtained' => 70,
        ]);

        // A teacher without an assignment for the paper is refused.
        $this->actingAs($admin)->post(route('assignments.toggle', TeacherAssignment::where('staff_id', $staffA->id)->firstOrFail()))->assertRedirect();

        $this->actingAs($teacherA)->post(route('exams.marks-store'), [
            'examination_subject_id' => $paperA->id,
            'section_id' => $section->id,
            'marks' => [$student->id => 85],
        ])->assertForbidden();

        $this->assertDatabaseHas('marks', [
            'examination_subject_id' => $paperA->id,
            'marks_obtained' => 80,
        ]);
    }

    public function test_marks_follow_the_draft_submitted_approved_workflow_and_lock_after_submission(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();
        [$student, $enrollment, $class, $section] = $this->createStudent($session);
        [$teacher, $staff] = $this->teacher('workflow.teacher@example.com');
        $subject = Subject::query()->firstOrFail();

        $exam = $this->exam($session, 'Workflow Exam');
        $paper = $this->paper($exam, $class, $subject);
        $this->assign($session, $class, $section, $subject, $staff);

        $this->actingAs($teacher)->post(route('exams.marks-store'), [
            'examination_subject_id' => $paper->id,
            'section_id' => $section->id,
            'marks' => [$student->id => 75],
        ])->assertRedirect();

        $mark = Mark::query()->where('examination_subject_id', $paper->id)->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('draft', $mark->status);
        $this->assertSame($teacher->id, $mark->entered_by);

        // Submit the draft marks.
        $this->actingAs($teacher)->post(route('exams.marks-submit'), [
            'examination_subject_id' => $paper->id,
            'section_id' => $section->id,
        ])->assertRedirect();

        $mark->refresh();
        $this->assertSame('submitted', $mark->status);
        $this->assertSame($teacher->id, $mark->submitted_by);

        // A teacher cannot silently overwrite submitted marks.
        $this->actingAs($teacher)->post(route('exams.marks-store'), [
            'examination_subject_id' => $paper->id,
            'section_id' => $section->id,
            'marks' => [$student->id => 90],
        ])->assertForbidden();

        $this->assertSame('75.00', $mark->fresh()->marks_obtained);

        // The administrator approves the marks.
        $this->actingAs($admin)->post(route('exams.marks-approve'), [
            'examination_subject_id' => $paper->id,
            'section_id' => $section->id,
        ])->assertRedirect();

        $mark->refresh();
        $this->assertSame('approved', $mark->status);
        $this->assertSame($admin->id, $mark->approved_by);

        // Teachers still cannot change approved marks.
        $this->actingAs($teacher)->post(route('exams.marks-store'), [
            'examination_subject_id' => $paper->id,
            'section_id' => $section->id,
            'marks' => [$student->id => 95],
        ])->assertForbidden();

        // Corrections to approved marks need a reason and are audited.
        $this->actingAs($admin)->post(route('exams.marks-store'), [
            'examination_subject_id' => $paper->id,
            'section_id' => $section->id,
            'marks' => [$student->id => 95],
        ])->assertSessionHasErrors('correction_reason');

        $this->assertSame('75.00', $mark->fresh()->marks_obtained);

        // An authorised administrator can correct; the workflow restarts at draft.
        $this->actingAs($admin)->post(route('exams.marks-store'), [
            'examination_subject_id' => $paper->id,
            'section_id' => $section->id,
            'marks' => [$student->id => 95],
            'correction_reason' => 'Rechecked the answer script with the subject teacher.',
        ])->assertRedirect();

        $mark->refresh();
        $this->assertSame('95.00', $mark->marks_obtained);
        $this->assertSame('draft', $mark->status);
        $this->assertNull($mark->approved_by);

        $this->assertDatabaseHas('audit_logs', ['action' => 'marks_submitted']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'marks_approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'marks_corrected']);
    }

    public function test_administrator_can_link_and_unlink_a_staff_profile_from_a_user_account(): void
    {
        $this->seedCore();
        $admin = $this->admin();
        $role = Role::query()->where('slug', 'teacher')->firstOrFail();

        $staff = Staff::create([
            'employee_no' => 'EMP-LINK-001',
            'first_name' => 'Unlinked',
            'last_name' => 'Teacher',
            'gender' => 'female',
            'phone' => '+880 1700 111222',
            'joining_date' => now()->subYear()->toDateString(),
            'employment_type' => 'full_time',
            'status' => 'active',
            'basic_salary' => 30000,
        ]);

        $user = User::create([
            'role_id' => $role->id,
            'name' => 'Unlinked Teacher Account',
            'email' => 'unlinked.teacher@example.com',
            'password' => 'Secret@12345',
            'is_active' => true,
        ]);

        $this->assertNull($staff->fresh()->user_id);

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $role->id,
            'is_active' => 1,
            'staff_id' => $staff->id,
        ])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();

        $this->assertSame($user->id, $staff->fresh()->user_id);

        // A staff profile cannot be stolen by another account.
        $other = User::create([
            'role_id' => $role->id,
            'name' => 'Second Teacher Account',
            'email' => 'second.teacher@example.com',
            'password' => 'Secret@12345',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('users.update', $other), [
            'name' => $other->name,
            'email' => $other->email,
            'role_id' => $role->id,
            'is_active' => 1,
            'staff_id' => $staff->id,
        ])->assertSessionHasErrors('staff_id');

        $this->assertSame($user->id, $staff->fresh()->user_id);

        // Removing the link keeps the staff record but blocks teacher access.
        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $role->id,
            'is_active' => 1,
            'staff_id' => '',
        ])->assertRedirect(route('users.index'));

        $this->assertNull($staff->fresh()->user_id);
        $this->assertDatabaseHas('staff', ['id' => $staff->id, 'employee_no' => 'EMP-LINK-001']);
    }

    public function test_teacher_dashboard_only_lists_their_own_assignments(): void
    {
        $session = $this->seedCore();
        [$student, $enrollment, $class, $section] = $this->createStudent($session);
        [$teacherA, $staffA] = $this->teacher('teacher.a@example.com');
        [$teacherB, $staffB] = $this->teacher('teacher.b@example.com');

        $subject = Subject::query()->firstOrFail();
        $this->assign($session, $class, $section, $subject, $staffA);

        $response = $this->actingAs($teacherA)->get(route('teacher.dashboard'))->assertOk();
        $response->assertSee($subject->name);
        $response->assertSee($class->name);

        $other = $this->actingAs($teacherB)->get(route('teacher.dashboard'))->assertOk();
        $other->assertDontSee($subject->name);
        $other->assertSee('You have no active assignments');
    }

    private function admin(): User
    {
        return User::query()->where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();
    }

    /**
     * @return array{0: User, 1: Staff}
     */
    private function teacher(string $email): array
    {
        $role = Role::query()->where('slug', 'teacher')->firstOrFail();

        $staff = Staff::create([
            'employee_no' => 'EMP-'.strtoupper(substr(md5($email), 0, 8)),
            'first_name' => 'Teacher',
            'last_name' => substr(md5($email), 0, 5),
            'gender' => 'female',
            'phone' => '+880 1700 000'.random_int(100, 999),
            'joining_date' => now()->subYear()->toDateString(),
            'employment_type' => 'full_time',
            'status' => 'active',
            'basic_salary' => 30000,
        ]);

        $user = User::create([
            'role_id' => $role->id,
            'name' => 'Teacher '.substr(md5($email), 0, 5),
            'email' => $email,
            'password' => 'Secret@12345',
            'is_active' => true,
        ]);

        $staff->update(['user_id' => $user->id]);

        return [$user, $staff];
    }

    private function exam(AcademicSession $session, string $name): Examination
    {
        return Examination::create([
            'academic_session_id' => $session->id,
            'name' => $name,
            'exam_type' => 'terminal',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-10',
            'status' => 'draft',
        ]);
    }

    private function paper(Examination $exam, SchoolClass $class, Subject $subject): ExaminationSubject
    {
        return $exam->subjects()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'full_marks' => 100,
            'pass_marks' => 33,
            'exam_date' => '2026-11-02',
        ]);
    }

    private function assign(AcademicSession $session, SchoolClass $class, Section $section, Subject $subject, Staff $staff): TeacherAssignment
    {
        return TeacherAssignment::create([
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'staff_id' => $staff->id,
            'starts_on' => now()->subWeek()->toDateString(),
            'is_active' => true,
        ]);
    }
}
