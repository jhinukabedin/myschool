<?php

namespace Tests\Concerns;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use Database\Seeders\CoreDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Str;

trait BuildsSchoolData
{
    /**
     * Seed the canonical roles, settings and core academic structure without
     * the large demo dataset. This keeps feature tests fast and deterministic.
     */
    protected function seedCore(): AcademicSession
    {
        $this->seed([
            RolePermissionSeeder::class,
            SettingSeeder::class,
            CoreDataSeeder::class,
        ]);

        return AcademicSession::query()->where('name', '2026')->firstOrFail();
    }

    /**
     * @return array{0: Student, 1: Enrollment, 2: SchoolClass, 3: Section}
     */
    protected function createStudent(AcademicSession $session, string $firstName = 'Test'): array
    {
        $class = SchoolClass::query()->where('numeric_level', '>=', 3)->orderBy('numeric_level')->firstOrFail();
        $section = $class->sections()->orderBy('name')->firstOrFail();

        $student = Student::create([
            'admission_no' => 'TEST-'.Str::upper(Str::random(8)),
            'first_name' => $firstName,
            'last_name' => 'Student',
            'gender' => 'female',
            'date_of_birth' => '2015-04-12',
            'phone' => '+880 1700 000000',
            'address' => 'Dhanmondi, Dhaka',
            'admission_date' => $session->start_date->toDateString(),
            'status' => 'active',
        ]);

        $rollNo = (int) Enrollment::query()
            ->where('academic_session_id', $session->id)
            ->where('class_id', $class->id)
            ->where('section_id', $section->id)
            ->max('roll_no') + 1;

        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_no' => $rollNo,
            'status' => 'active',
        ]);

        return [$student, $enrollment, $class, $section];
    }
}
