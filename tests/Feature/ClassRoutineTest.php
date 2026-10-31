<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\Role;
use App\Models\RoutineEntry;
use App\Models\RoutineTimeSlot;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentPortalLink;
use App\Models\Subject;
use App\Models\User;
use App\Services\RoutineScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchoolData;
use Tests\TestCase;

class ClassRoutineTest extends TestCase
{
    use BuildsSchoolData;
    use RefreshDatabase;

    public function test_timetable_orientation_keeps_days_as_rows_and_slots_as_columns(): void
    {
        [$session, $class, $section, $admin] = $this->context();
        $routine = $this->makeRoutine($session, $class, $section, $admin);

        $schedule = app(RoutineScheduleService::class);
        $schedule->saveWorkingDays(['monday', 'wednesday']);
        $slots = $schedule->activeSlots();

        $html = $this->actingAs($admin)
            ->get(route('routines.show', $routine))
            ->assertOk()
            ->assertSee('Weekly timetable')
            ->getContent();

        $this->assertLessThan(
            strpos($html, 'routine-slot-name'),
            strpos($html, 'routine-axis-label">Day'),
            'The Day column must be declared before the time slot columns.'
        );

        $this->assertSame(2, substr_count($html, 'routine-day-col" scope="row"'));
        $this->assertStringContainsString('routine-day-name">Monday', $html);
        $this->assertStringContainsString('routine-day-name">Wednesday', $html);
        $this->assertStringNotContainsString('routine-day-name">Tuesday', $html);

        $this->assertSame($slots->count(), substr_count($html, 'routine-slot-name">'));

        $firstMonday = strpos($html, 'routine-day-name">Monday');
        $firstWednesday = strpos($html, 'routine-day-name">Wednesday');
        $this->assertLessThan($firstWednesday, $firstMonday, 'Configured day order must control row order.');
    }

    public function test_days_and_time_slots_are_driven_by_configuration_not_hardcoded_rows(): void
    {
        [$session, $class, $section, $admin] = $this->context();
        $routine = $this->makeRoutine($session, $class, $section, $admin);

        $schedule = app(RoutineScheduleService::class);
        $schedule->saveWorkingDays(['thursday', 'saturday'], [
            'thursday' => 2,
            'saturday' => 1,
        ]);

        RoutineTimeSlot::query()->update(['is_active' => false]);

        $this->actingAs($admin)->post(route('routines.time-slots.store'), [
            'name' => 'Custom period A',
            'start_time' => '06:00',
            'end_time' => '06:40',
            'type' => 'class',
            'sort_order' => 1,
        ])->assertRedirect(route('routines.settings'));

        $this->actingAs($admin)->post(route('routines.time-slots.store'), [
            'name' => 'Custom lunch',
            'start_time' => '06:40',
            'end_time' => '07:10',
            'type' => 'lunch',
            'sort_order' => 2,
        ])->assertRedirect(route('routines.settings'));

        $html = $this->actingAs($admin)->get(route('routines.show', $routine))->assertOk()->getContent();

        $this->assertStringContainsString('routine-slot-name">Custom period A', $html);
        $this->assertStringContainsString('routine-slot-name">Custom lunch', $html);
        $this->assertStringContainsString('routine-day-name">Saturday', $html);
        $this->assertStringContainsString('routine-day-name">Thursday', $html);
        $this->assertStringNotContainsString('routine-day-name">Monday', $html);
        $this->assertLessThan(
            strpos($html, 'routine-day-name">Thursday'),
            strpos($html, 'routine-day-name">Saturday'),
            'The configured display order must decide which day row is first.'
        );
    }

    public function test_a_class_routine_can_be_created_edited_and_duplicates_are_rejected(): void
    {
        [$session, $class, $section, $admin] = $this->context();

        $payload = [
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'effective_from' => '2026-02-01',
            'effective_to' => '2026-12-20',
            'notes' => 'First published routine.',
        ];

        $this->actingAs($admin)->post(route('routines.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $routine = ClassRoutine::query()->firstOrFail();
        $this->assertSame('2026-02-01', $routine->effective_from->format('Y-m-d'));

        $this->actingAs($admin)->post(route('routines.store'), $payload)
            ->assertSessionHasErrors('class_id');

        $this->assertSame(1, ClassRoutine::query()->count());

        $this->actingAs($admin)->get(route('routines.edit', $routine))
            ->assertOk()
            ->assertSee('First published routine.');

        $this->actingAs($admin)->put(route('routines.update', $routine), [
            ...$payload,
            'effective_from' => '2026-03-01',
            'effective_to' => null,
            'notes' => 'Revised routine.',
        ])->assertRedirect(route('routines.show', $routine));

        $routine->refresh();
        $this->assertSame('2026-03-01', $routine->effective_from->format('Y-m-d'));
        $this->assertNull($routine->effective_to);
        $this->assertSame('Revised routine.', $routine->notes);
    }

    public function test_a_routine_can_be_copied_to_another_academic_year_with_its_cells(): void
    {
        [$session, $class, $section, $admin] = $this->context();
        $routine = $this->makeRoutine($session, $class, $section, $admin);
        [$teacher] = $this->teacher('copy.teacher@example.com');
        $subject = Subject::query()->firstOrFail();
        $slot = $this->teachingSlot();
        $day = $this->firstWorkingDay();

        RoutineEntry::create([
            'class_routine_id' => $routine->id,
            'day' => $day,
            'routine_time_slot_id' => $slot->id,
            'subject_id' => $subject->id,
            'staff_id' => $teacher->id,
            'classroom' => 'Room 401',
        ]);

        $previousSession = AcademicSession::query()->where('name', '2025')->firstOrFail();

        $this->actingAs($admin)->post(route('routines.copy.store', $routine), [
            'academic_session_id' => $previousSession->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'effective_from' => '2025-01-01',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $copy = ClassRoutine::query()
            ->where('academic_session_id', $previousSession->id)
            ->firstOrFail();

        $copiedEntry = $copy->entries()->firstOrFail();
        $this->assertSame($subject->id, $copiedEntry->subject_id);
        $this->assertSame($teacher->id, $copiedEntry->staff_id);
        $this->assertSame('Room 401', $copiedEntry->classroom);
        $this->assertSame($day, $copiedEntry->day);

        $this->actingAs($admin)->post(route('routines.copy.store', $routine), [
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'effective_from' => '2026-01-01',
        ])->assertSessionHasErrors('class_id');
    }

    public function test_time_slots_can_be_added_edited_reordered_and_removed(): void
    {
        [$session, $class, $section, $admin] = $this->context();
        $routine = $this->makeRoutine($session, $class, $section, $admin);
        [$teacher] = $this->teacher('slot.manager@example.com');
        $subject = Subject::query()->firstOrFail();
        $day = $this->firstWorkingDay();

        $this->actingAs($admin)->post(route('routines.time-slots.store'), [
            'name' => 'Period 7',
            'start_time' => '14:00',
            'end_time' => '14:45',
            'type' => 'class',
            'is_active' => 1,
        ])->assertRedirect(route('routines.settings'))->assertSessionHasNoErrors();

        $slot = RoutineTimeSlot::query()->where('name', 'Period 7')->firstOrFail();
        $this->assertTrue($slot->is_active);

        $this->actingAs($admin)->put(route('routines.time-slots.update', $slot), [
            'name' => 'Period Seven',
            'start_time' => '14:00',
            'end_time' => '14:45',
            'type' => 'class',
            'sort_order' => $slot->sort_order,
            'is_active' => 1,
        ])->assertRedirect(route('routines.settings'))->assertSessionHasNoErrors();

        $slot->refresh();
        $this->assertSame('Period Seven', $slot->name);
        $oldOrder = $slot->sort_order;

        $this->actingAs($admin)->post(route('routines.time-slots.move', $slot), [
            'direction' => 'up',
        ])->assertRedirect(route('routines.settings'));

        $this->assertLessThan(
            $oldOrder,
            $slot->fresh()->sort_order,
            'Moving a period up must move it towards the start of the timetable.'
        );

        // A period that holds timetable cells cannot be deleted until it is cleared.
        $this->actingAs($admin)->post(route('routines.cells.save', $routine), [
            'day' => $day,
            'routine_time_slot_id' => $slot->id,
            'subject_id' => $subject->id,
            'staff_id' => $teacher->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->delete(route('routines.time-slots.destroy', $slot))
            ->assertSessionHasErrors('slot');
        $this->assertDatabaseHas('routine_time_slots', ['id' => $slot->id]);

        $this->actingAs($admin)->post(route('routines.cells.clear', $routine), [
            'day' => $day,
            'routine_time_slot_id' => $slot->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->delete(route('routines.time-slots.destroy', $slot))
            ->assertRedirect(route('routines.settings'))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('routine_time_slots', ['id' => $slot->id]);
    }

    public function test_cells_can_be_assigned_edited_and_cleared_without_deleting_the_routine(): void
    {
        [$session, $class, $section, $admin] = $this->context();
        $routine = $this->makeRoutine($session, $class, $section, $admin);
        [$firstTeacher] = $this->teacher('cell.teacher.one@example.com');
        [$secondTeacher] = $this->teacher('cell.teacher.two@example.com');

        $subjects = Subject::query()->orderBy('id')->limit(2)->get();
        $slot = $this->teachingSlot();
        $day = $this->firstWorkingDay();

        $this->actingAs($admin)->post(route('routines.cells.save', $routine), [
            'day' => $day,
            'routine_time_slot_id' => $slot->id,
            'subject_id' => $subjects[0]->id,
            'staff_id' => $firstTeacher->id,
            'classroom' => 'Room 204',
            'notes' => 'Bring the lab manual.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $entry = RoutineEntry::query()->firstOrFail();
        $this->assertSame($day, $entry->day);

        $this->actingAs($admin)->get(route('routines.show', $routine))
            ->assertOk()
            ->assertSee($subjects[0]->name)
            ->assertSee($firstTeacher->full_name)
            ->assertSee('Room 204')
            ->assertSee('Bring the lab manual.');

        $this->actingAs($admin)->post(route('routines.cells.save', $routine), [
            'day' => $day,
            'routine_time_slot_id' => $slot->id,
            'subject_id' => $subjects[1]->id,
            'staff_id' => $secondTeacher->id,
            'classroom' => 'Room 205',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, RoutineEntry::query()->count());
        $entry->refresh();
        $this->assertSame($subjects[1]->id, $entry->subject_id);
        $this->assertSame($secondTeacher->id, $entry->staff_id);
        $this->assertSame('Room 205', $entry->classroom);

        $this->actingAs($admin)->post(route('routines.cells.clear', $routine), [
            'day' => $day,
            'routine_time_slot_id' => $slot->id,
        ])->assertRedirect();

        $this->assertSame(0, RoutineEntry::query()->count());
        $this->assertSame(1, ClassRoutine::query()->count());
        $this->assertTrue($routine->fresh()->exists);
    }

    public function test_lunch_and_break_periods_render_in_every_day_row_and_cannot_hold_a_subject(): void
    {
        [$session, $class, $section, $admin] = $this->context();
        $routine = $this->makeRoutine($session, $class, $section, $admin);
        $lunch = RoutineTimeSlot::query()->where('type', 'lunch')->firstOrFail();
        $dayCount = count(app(RoutineScheduleService::class)->workingDays());
        $breakSlotCount = RoutineTimeSlot::query()->active()->where('type', '!=', 'class')->count();

        $html = $this->actingAs($admin)->get(route('routines.show', $routine))->assertOk()->getContent();

        $this->assertSame($dayCount, substr_count($html, 'LUNCH BREAK'));
        $this->assertSame($dayCount * $breakSlotCount, substr_count($html, 'class="routine-break"'));

        [$teacher] = $this->teacher('lunch.teacher@example.com');

        $this->actingAs($admin)->post(route('routines.cells.save', $routine), [
            'day' => $this->firstWorkingDay(),
            'routine_time_slot_id' => $lunch->id,
            'subject_id' => Subject::query()->value('id'),
            'staff_id' => $teacher->id,
        ])->assertSessionHasErrors('routine_time_slot_id');

        $this->assertSame(0, RoutineEntry::query()->count());
    }

    public function test_teacher_overlap_is_rejected_across_class_routines(): void
    {
        [$session, $classA, $sectionA, $admin] = $this->context();
        [$classB, $sectionB] = $this->secondClassAndSection();
        $routineA = $this->makeRoutine($session, $classA, $sectionA, $admin);
        $routineB = $this->makeRoutine($session, $classB, $sectionB, $admin);
        [$teacher] = $this->teacher('overlap.teacher@example.com');
        [$otherTeacher] = $this->teacher('overlap.other@example.com');
        $slot = $this->teachingSlot();
        $day = $this->firstWorkingDay();
        $subject = Subject::query()->firstOrFail();

        $this->actingAs($admin)->post(route('routines.cells.save', $routineA), [
            'day' => $day,
            'routine_time_slot_id' => $slot->id,
            'subject_id' => $subject->id,
            'staff_id' => $teacher->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('routines.cells.save', $routineB), [
            'day' => $day,
            'routine_time_slot_id' => $slot->id,
            'subject_id' => $subject->id,
            'staff_id' => $teacher->id,
        ])->assertSessionHasErrors('staff_id');

        $this->assertSame(1, RoutineEntry::query()->where('staff_id', $teacher->id)->count());

        $this->actingAs($admin)->post(route('routines.cells.save', $routineB), [
            'day' => $day,
            'routine_time_slot_id' => $slot->id,
            'subject_id' => $subject->id,
            'staff_id' => $otherTeacher->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, RoutineEntry::query()->where('routine_time_slot_id', $slot->id)->count());
    }

    public function test_classroom_overlap_is_rejected_ignoring_case_and_spacing(): void
    {
        [$session, $classA, $sectionA, $admin] = $this->context();
        [$classB, $sectionB] = $this->secondClassAndSection();
        $routineA = $this->makeRoutine($session, $classA, $sectionA, $admin);
        $routineB = $this->makeRoutine($session, $classB, $sectionB, $admin);
        [$teacherA] = $this->teacher('room.teacher.a@example.com');
        [$teacherB] = $this->teacher('room.teacher.b@example.com');
        $slot = $this->teachingSlot();
        $day = $this->firstWorkingDay();
        $subject = Subject::query()->firstOrFail();

        $this->actingAs($admin)->post(route('routines.cells.save', $routineA), [
            'day' => $day,
            'routine_time_slot_id' => $slot->id,
            'subject_id' => $subject->id,
            'staff_id' => $teacherA->id,
            'classroom' => 'Room 101',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('routines.cells.save', $routineB), [
            'day' => $day,
            'routine_time_slot_id' => $slot->id,
            'subject_id' => $subject->id,
            'staff_id' => $teacherB->id,
            'classroom' => '  room 101  ',
        ])->assertSessionHasErrors('classroom');

        $this->actingAs($admin)->post(route('routines.cells.save', $routineB), [
            'day' => $day,
            'routine_time_slot_id' => $slot->id,
            'subject_id' => $subject->id,
            'staff_id' => $teacherB->id,
            'classroom' => 'Room 102',
        ])->assertSessionHasNoErrors();
    }

    public function test_overlapping_periods_and_duplicate_class_routines_are_rejected(): void
    {
        [$session, $class, $section, $admin] = $this->context();
        $routine = $this->makeRoutine($session, $class, $section, $admin);
        [$teacher] = $this->teacher('class.conflict@example.com');
        [$otherTeacher] = $this->teacher('class.conflict.other@example.com');
        $subject = Subject::query()->firstOrFail();
        $slot = $this->teachingSlot();

        $this->actingAs($admin)->post(route('routines.time-slots.store'), [
            'name' => 'Overlapping period',
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'type' => 'class',
            'sort_order' => 90,
        ])->assertSessionHasErrors('start_time');

        $overlap = RoutineTimeSlot::create([
            'name' => 'Overlap period',
            'start_time' => '10:15',
            'end_time' => '10:30',
            'type' => 'class',
            'sort_order' => 91,
            'is_active' => true,
        ]);

        $periodAtTen = RoutineTimeSlot::query()
            ->where('type', 'class')
            ->where('start_time', '<=', '10:00')
            ->where('end_time', '>=', '10:45')
            ->firstOrFail();

        $this->actingAs($admin)->post(route('routines.cells.save', $routine), [
            'day' => $this->firstWorkingDay(),
            'routine_time_slot_id' => $periodAtTen->id,
            'subject_id' => $subject->id,
            'staff_id' => $teacher->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('routines.cells.save', $routine), [
            'day' => $this->firstWorkingDay(),
            'routine_time_slot_id' => $overlap->id,
            'subject_id' => $subject->id,
            'staff_id' => $otherTeacher->id,
        ])->assertSessionHasErrors('routine_time_slot_id');

        $this->assertSame(1, RoutineEntry::query()->count());

        $this->actingAs($admin)->post(route('routines.store'), [
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'effective_from' => '2026-01-05',
        ])->assertSessionHasErrors('class_id');
    }

    public function test_students_only_see_the_routine_for_their_enrolled_class_and_section(): void
    {
        [$session, $classA, $sectionA, $admin] = $this->context();
        [$classB, $sectionB] = $this->secondClassAndSection();
        $student = $this->studentFor($session, $classA, $sectionA);
        $user = $this->studentUser($student);

        $routineA = $this->makeRoutine($session, $classA, $sectionA, $admin);
        $routineB = $this->makeRoutine($session, $classB, $sectionB, $admin);
        [$teacherA] = $this->teacher('student.own@example.com');
        [$teacherB] = $this->teacher('student.other@example.com');
        $subjects = Subject::query()->orderBy('id')->limit(2)->get();
        $slot = $this->teachingSlot();
        $day = $this->firstWorkingDay();

        RoutineEntry::create([
            'class_routine_id' => $routineA->id,
            'day' => $day,
            'routine_time_slot_id' => $slot->id,
            'subject_id' => $subjects[0]->id,
            'staff_id' => $teacherA->id,
            'classroom' => 'Room 11',
        ]);

        RoutineEntry::create([
            'class_routine_id' => $routineB->id,
            'day' => $day,
            'routine_time_slot_id' => $slot->id,
            'subject_id' => $subjects[1]->id,
            'staff_id' => $teacherB->id,
            'classroom' => 'Room 12',
        ]);

        $this->actingAs($user)->get(route('student.routine'))
            ->assertOk()
            ->assertSee($subjects[0]->name)
            ->assertSee($teacherA->full_name)
            ->assertSee('Room 11')
            ->assertDontSee($subjects[1]->name)
            ->assertDontSee($teacherB->full_name)
            ->assertDontSee('Room 12');
    }

    public function test_students_see_an_empty_state_when_their_class_has_no_published_routine(): void
    {
        [$session, $class, $section, $admin] = $this->context();
        [$otherClass, $otherSection] = $this->secondClassAndSection();
        $student = $this->studentFor($session, $class, $section);
        $user = $this->studentUser($student);

        $this->makeRoutine($session, $otherClass, $otherSection, $admin);

        $this->actingAs($user)->get(route('student.routine'))
            ->assertOk()
            ->assertSee('No published class routine yet.')
            ->assertDontSee('portal-routine-table', false)
            ->assertDontSee('LUNCH BREAK');
    }

    public function test_teachers_only_see_their_own_schedule_and_cannot_modify_routines(): void
    {
        [$session, $class, $section, $admin] = $this->context();
        $routine = $this->makeRoutine($session, $class, $section, $admin);
        [$ownStaff, $teacher] = $this->teacher('schedule.own@example.com');
        [$otherStaff] = $this->teacher('schedule.other@example.com');
        $subjects = Subject::query()->orderBy('id')->limit(2)->get();
        $slots = RoutineTimeSlot::query()->teaching()->ordered()->limit(2)->get();
        $day = $this->firstWorkingDay();

        RoutineEntry::create([
            'class_routine_id' => $routine->id,
            'day' => $day,
            'routine_time_slot_id' => $slots[0]->id,
            'subject_id' => $subjects[0]->id,
            'staff_id' => $ownStaff->id,
        ]);

        RoutineEntry::create([
            'class_routine_id' => $routine->id,
            'day' => $day,
            'routine_time_slot_id' => $slots[1]->id,
            'subject_id' => $subjects[1]->id,
            'staff_id' => $otherStaff->id,
        ]);

        $this->actingAs($teacher)->get(route('teacher.schedule'))
            ->assertOk()
            ->assertSee('My Class Schedule')
            ->assertSee($subjects[0]->name)
            ->assertDontSee($subjects[1]->name)
            ->assertDontSee($otherStaff->full_name);

        $this->actingAs($teacher)->get(route('routines.index'))->assertOk();
        $this->actingAs($teacher)->get(route('routines.create'))->assertForbidden();
        $this->actingAs($teacher)->post(route('routines.cells.save', $routine), [
            'day' => $day,
            'routine_time_slot_id' => $slots[1]->id,
            'subject_id' => $subjects[0]->id,
            'staff_id' => $ownStaff->id,
        ])->assertForbidden();

        $this->assertSame(2, RoutineEntry::query()->count());
    }

    public function test_print_and_pdf_views_use_landscape_days_as_rows_and_slot_columns(): void
    {
        [$session, $class, $section, $admin] = $this->context();
        $routine = $this->makeRoutine($session, $class, $section, $admin);
        [$teacher] = $this->teacher('print.teacher@example.com');
        $subject = Subject::query()->firstOrFail();
        $slot = $this->teachingSlot();

        RoutineEntry::create([
            'class_routine_id' => $routine->id,
            'day' => $this->firstWorkingDay(),
            'routine_time_slot_id' => $slot->id,
            'subject_id' => $subject->id,
            'staff_id' => $teacher->id,
            'classroom' => 'Room 301',
        ]);

        $print = $this->actingAs($admin)->get(route('routines.print', $routine))->assertOk();
        $print->assertSee('size: A4 landscape', false)
            ->assertSee('routine-axis-label">Day', false)
            ->assertSee('routine-slot-name">'.$slot->name, false)
            ->assertSee($subject->name)
            ->assertSee($teacher->full_name)
            ->assertSee('Room 301');

        $pdf = $this->actingAs($admin)->get(route('routines.pdf', $routine))->assertOk();
        $pdf->assertSee('window.print()', false)
            ->assertSee('size: A4 landscape', false);

        $this->assertSame(
            count(app(RoutineScheduleService::class)->workingDays()),
            substr_count($print->getContent(), 'routine-day-col" scope="row"')
        );
    }

    public function test_routine_permissions_are_enforced_for_view_create_edit_copy_print_and_delete(): void
    {
        [$session, $class, $section, $admin] = $this->context();
        $routine = $this->makeRoutine($session, $class, $section, $admin);

        $accountantRole = Role::query()->where('slug', 'accountant')->firstOrFail();
        $accountant = User::create([
            'role_id' => $accountantRole->id,
            'name' => 'Accounts User',
            'email' => 'routine.accountant@example.com',
            'password' => 'Secret@12345',
            'is_active' => true,
        ]);

        $this->actingAs($accountant)->get(route('routines.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('routines.show', $routine))->assertForbidden();
        $this->actingAs($accountant)->get(route('routines.print', $routine))->assertForbidden();
        $this->actingAs($accountant)->post(route('routines.store'), [])->assertForbidden();
        $this->actingAs($accountant)->post(route('routines.cells.clear', $routine), [])->assertForbidden();

        $registrarRole = Role::query()->where('slug', 'registrar')->firstOrFail();
        $registrar = User::create([
            'role_id' => $registrarRole->id,
            'name' => 'Registrar User',
            'email' => 'routine.registrar@example.com',
            'password' => 'Secret@12345',
            'is_active' => true,
        ]);

        $this->actingAs($registrar)->get(route('routines.index'))->assertOk();
        $this->actingAs($registrar)->get(route('routines.create'))->assertForbidden();
        $this->actingAs($registrar)->get(route('routines.print', $routine))->assertForbidden();
        $this->actingAs($registrar)->get(route('routines.copy', $routine))->assertForbidden();

        $this->actingAs($admin)->get(route('routines.create'))->assertOk();
        $this->actingAs($admin)->get(route('routines.copy', $routine))->assertOk();
        $this->actingAs($admin)->get(route('routines.print', $routine))->assertOk();
        $this->actingAs($admin)->get(route('routines.settings'))->assertOk();
    }

    /**
     * @return array{0: AcademicSession, 1: SchoolClass, 2: Section, 3: User}
     */
    private function context(): array
    {
        $session = $this->seedCore();
        $class = SchoolClass::query()->where('numeric_level', '>=', 3)->orderBy('numeric_level')->firstOrFail();
        $section = $class->sections()->orderBy('name')->firstOrFail();
        $admin = User::query()->whereEmail(env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();

        return [$session, $class, $section, $admin];
    }

    /**
     * @return array{0: SchoolClass, 1: Section}
     */
    private function secondClassAndSection(): array
    {
        $class = SchoolClass::query()->where('numeric_level', '>=', 3)->orderBy('numeric_level')->firstOrFail();
        $section = $class->sections()->orderByDesc('name')->firstOrFail();

        return [$class, $section];
    }

    private function makeRoutine(
        AcademicSession $session,
        SchoolClass $class,
        Section $section,
        User $admin
    ): ClassRoutine {
        return ClassRoutine::create([
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'effective_from' => $session->start_date->toDateString(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    /**
     * @return array{0: Staff, 1: User}
     */
    private function teacher(string $email): array
    {
        $role = Role::query()->where('slug', 'teacher')->firstOrFail();

        $staff = Staff::create([
            'employee_no' => 'EMP-'.strtoupper(substr(md5($email), 0, 8)),
            'first_name' => 'Timetable',
            'last_name' => substr(md5($email), 0, 5),
            'gender' => 'female',
            'phone' => '+880 1700 00'.random_int(1000, 9999),
            'joining_date' => now()->subYear()->toDateString(),
            'employment_type' => 'full_time',
            'status' => 'active',
            'basic_salary' => 30000,
        ]);

        $user = User::create([
            'role_id' => $role->id,
            'name' => $staff->full_name,
            'email' => $email,
            'password' => 'Secret@12345',
            'is_active' => true,
        ]);

        $staff->update(['user_id' => $user->id]);

        return [$staff, $user];
    }

    private function studentFor(AcademicSession $session, SchoolClass $class, Section $section): Student
    {
        $student = Student::create([
            'admission_no' => 'TT-'.strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'first_name' => 'Timetable',
            'last_name' => 'Student',
            'gender' => 'male',
            'date_of_birth' => '2015-04-12',
            'phone' => '+880 1700 000000',
            'address' => 'Dhanmondi, Dhaka',
            'admission_date' => $session->start_date->toDateString(),
            'status' => 'active',
        ]);

        \App\Models\Enrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_no' => 1,
            'status' => 'active',
        ]);

        return $student;
    }

    private function studentUser(Student $student): User
    {
        $role = Role::query()->where('slug', 'student')->firstOrFail();

        $user = User::create([
            'role_id' => $role->id,
            'name' => $student->full_name,
            'email' => 'routine.student@example.com',
            'password' => 'Secret@12345',
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

    private function teachingSlot(): RoutineTimeSlot
    {
        return RoutineTimeSlot::query()->active()->teaching()->ordered()->firstOrFail();
    }

    private function firstWorkingDay(): string
    {
        return array_key_first(app(RoutineScheduleService::class)->workingDays());
    }
}
