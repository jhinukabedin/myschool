<?php

namespace Tests\Feature;

use App\Models\ClassRoutine;
use App\Models\Notice;
use App\Models\Role;
use App\Models\RoutineEntry;
use App\Models\RoutineTimeSlot;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentPortalLink;
use App\Models\Subject;
use App\Models\User;
use App\Models\WebsiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchoolData;
use Tests\TestCase;

class StudentPortalPagesTest extends TestCase
{
    use BuildsSchoolData;
    use RefreshDatabase;

    public function test_student_portal_pages_render_class_content_and_notices(): void
    {
        $session = $this->seedCore();
        [$student, $enrollment, $class, $section] = $this->createStudent($session, 'Portal');
        $user = $this->studentUser($student);

        $timeSlot = RoutineTimeSlot::query()->active()->teaching()->ordered()->firstOrFail();
        $subjects = Subject::query()->orderBy('id')->limit(2)->get();
        $teacher = Staff::create([
            'employee_no' => 'PORTAL-ROUTINE-1',
            'first_name' => 'Routine',
            'last_name' => 'Teacher',
            'gender' => 'female',
            'phone' => '+880 1700 555111',
            'joining_date' => now()->subYear()->toDateString(),
            'employment_type' => 'full_time',
            'status' => 'active',
            'basic_salary' => 30000,
        ]);
        $otherClass = \App\Models\SchoolClass::query()->where('id', '!=', $class->id)->firstOrFail();
        $otherTeacher = Staff::create([
            'employee_no' => 'PORTAL-ROUTINE-2',
            'first_name' => 'Other',
            'last_name' => 'Teacher',
            'gender' => 'male',
            'phone' => '+880 1700 555222',
            'joining_date' => now()->subYear()->toDateString(),
            'employment_type' => 'full_time',
            'status' => 'active',
            'basic_salary' => 30000,
        ]);

        $routine = ClassRoutine::create([
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'effective_from' => $session->start_date->toDateString(),
        ]);

        RoutineEntry::create([
            'class_routine_id' => $routine->id,
            'day' => 'sunday',
            'routine_time_slot_id' => $timeSlot->id,
            'subject_id' => $subjects[0]->id,
            'staff_id' => $teacher->id,
            'classroom' => 'Room 9A',
        ]);

        $otherRoutine = ClassRoutine::create([
            'academic_session_id' => $session->id,
            'class_id' => $otherClass->id,
            'section_id' => $otherClass->sections()->orderBy('name')->firstOrFail()->id,
            'effective_from' => $session->start_date->toDateString(),
        ]);

        RoutineEntry::create([
            'class_routine_id' => $otherRoutine->id,
            'day' => 'sunday',
            'routine_time_slot_id' => $timeSlot->id,
            'subject_id' => $subjects[1]->id,
            'staff_id' => $otherTeacher->id,
            'classroom' => 'Room 10B',
        ]);

        WebsiteContent::create([
            'type' => 'assignment',
            'title' => 'Chapter 5 practice',
            'subtitle' => 'Mathematics',
            'slug' => 'chapter-5-practice-test',
            'category' => $class->name,
            'excerpt' => 'Solve every problem.',
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'published',
            'published_at' => now(),
        ]);

        WebsiteContent::create([
            'type' => 'syllabus',
            'title' => 'Mathematics syllabus',
            'slug' => 'mathematics-syllabus-test',
            'category' => $class->name,
            'status' => 'published',
            'published_at' => now(),
        ]);

        WebsiteContent::create([
            'type' => 'download',
            'title' => 'School form',
            'slug' => 'school-form-test',
            'status' => 'published',
            'published_at' => now(),
        ]);

        WebsiteContent::create([
            'type' => 'academic_calendar',
            'title' => 'Annual sports day',
            'slug' => 'annual-sports-day-test',
            'excerpt' => 'Sports ground',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $notice = Notice::create([
            'title' => 'Annual examination notice',
            'body' => 'The annual examination starts next month.',
            'audience' => 'students',
            'publish_at' => now(),
            'status' => 'published',
        ]);

        $otherClassNotice = Notice::create([
            'title' => 'Other class notice',
            'body' => 'Only for another class.',
            'audience' => 'classes',
            'publish_at' => now(),
            'status' => 'published',
        ]);
        $otherClass = \App\Models\SchoolClass::query()->where('id', '!=', $class->id)->firstOrFail();
        $otherClassNotice->classes()->attach($otherClass->id);

        $this->actingAs($user)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Student Dashboard')
            ->assertSee('portal-sidebar', false)
            ->assertSee('Class Routine')
            ->assertSee('Study Materials');

        $this->actingAs($user)->get(route('student.profile'))->assertOk()->assertSee($student->full_name);
        $this->actingAs($user)->get(route('student.results'))->assertOk()->assertSee('আমার ফলাফল');

        $this->actingAs($user)->get(route('student.routine'))
            ->assertOk()
            ->assertSee('Weekly class routine')
            ->assertSee($subjects[0]->name)
            ->assertSee($teacher->full_name)
            ->assertSee('Room 9A')
            ->assertDontSee($subjects[1]->name)
            ->assertDontSee($otherTeacher->full_name)
            ->assertDontSee('Room 10B');

        $this->actingAs($user)->get(route('student.assignments'))
            ->assertOk()
            ->assertSee('Chapter 5 practice')
            ->assertSee('Mathematics');

        $this->actingAs($user)->get(route('student.materials'))
            ->assertOk()
            ->assertSee('Mathematics syllabus')
            ->assertSee('School form');

        $this->actingAs($user)->get(route('student.materials', ['q' => 'Mathematics']))
            ->assertOk()
            ->assertSee('Mathematics syllabus')
            ->assertDontSee('School form');

        $this->actingAs($user)->get(route('student.notices'))
            ->assertOk()
            ->assertSee($notice->title)
            ->assertDontSee($otherClassNotice->title);

        $this->actingAs($user)->get(route('student.notices.show', $notice))
            ->assertOk()
            ->assertSee($notice->body);

        $this->actingAs($user)->get(route('student.notices.show', $otherClassNotice))
            ->assertNotFound();

        $this->actingAs($user)->get(route('student.calendar'))
            ->assertOk()
            ->assertSee('Annual sports day');

        $this->actingAs($user)->get(route('student.downloads'))->assertOk()->assertSee('School form');
    }

    public function test_assignments_can_be_managed_from_website_administration_with_a_deadline(): void
    {
        $this->seedCore();
        $admin = User::query()->whereHas('role', fn ($query) => $query->where('slug', 'super-admin'))->firstOrFail();

        $this->actingAs($admin)->get(route('website-admin.assignments'))
            ->assertOk()
            ->assertSee('Assignments');

        $this->actingAs($admin)->post(route('website-content.store'), [
            'type' => 'assignment',
            'title' => 'Science lab report',
            'subtitle' => 'Science',
            'category' => 'Class 9',
            'excerpt' => 'Write the full report.',
            'due_date' => '2026-11-15',
            'body' => '<p>Include the method and result.</p>',
            'body_format' => 'html',
            'status' => 'published',
        ])->assertRedirect(route('website-content.index'));

        $assignment = WebsiteContent::query()->ofType('assignment')->firstOrFail();

        $this->assertSame('2026-11-15', $assignment->due_date?->format('Y-m-d'));
        $this->assertSame('Science', $assignment->subtitle);
    }

    private function studentUser(Student $student): User
    {
        $role = Role::query()->where('slug', 'student')->firstOrFail();

        $user = User::create([
            'role_id' => $role->id,
            'name' => $student->full_name,
            'email' => 'student.pages@example.test',
            'password' => bcrypt('Secret@12345'),
            'is_active' => true,
        ]);

        StudentPortalLink::create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'verified_at' => now(),
            'is_active' => true,
        ]);

        return $user;
    }
}
