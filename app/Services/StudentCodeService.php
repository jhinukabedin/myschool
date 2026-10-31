<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;

class StudentCodeService
{
    /**
     * Build and persist the permanent student ID.
     *
     * Format: academic year + class number + roll, e.g. 202601001 for
     * Class 1, roll 1 in the 2026 session. A collision (the same roll can
     * legitimately exist in two sections) advances the numeric suffix so the
     * ID always remains unique.
     */
    public function ensure(Student $student, ?Enrollment $enrollment = null): string
    {
        if (filled($student->student_code)) {
            return (string) $student->student_code;
        }

        $enrollment ??= $student->enrollments()
            ->with(['academicSession', 'class'])
            ->orderByDesc('academic_session_id')
            ->orderByDesc('id')
            ->first();

        $session = $enrollment?->academicSession;
        $class = $enrollment?->class;

        $year = $session?->start_date?->year;
        if (! $year) {
            $year = (int) (preg_match('/\d{4}/', (string) $session?->name, $matches)
                ? $matches[0]
                : now()->year);
        }

        $classCode = $this->classNumber($class);
        $roll = max(1, (int) ($enrollment?->roll_no ?? 1));
        $prefix = str_pad((string) $year, 4, '0', STR_PAD_LEFT)
            .str_pad((string) $classCode, 2, '0', STR_PAD_LEFT);

        $sequence = $roll;
        do {
            $code = $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $taken = Student::withTrashed()
                ->where('student_code', $code)
                ->when($student->exists, fn ($query) => $query->whereKeyNot($student->getKey()))
                ->exists();
            $sequence++;
        } while ($taken);

        $student->forceFill(['student_code' => $code])->save();

        return $code;
    }

    public function classNumber(?SchoolClass $class): int
    {
        if (! $class) {
            return 0;
        }

        if (preg_match('/(\d+)/', (string) $class->name, $matches)) {
            return max(0, (int) $matches[1]);
        }

        return max(0, (int) $class->numeric_level);
    }
}
