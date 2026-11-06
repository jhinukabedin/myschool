<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentCodeService
{
    /**
     * Permanent Student ID crew — YY + CLASS_CODE + SERIAL, digits only.
     *
     * CLASS_CODE is derived from the class's numeric_level: code = level + 1,
     * zero-padded to 2 digits. No hardcoded class mapping.
     *
     * The ID is assigned once at admission and stored as an immutable snapshot:
     * admission year (2 digits), class code (2 digits), and a per year+class
     * serial (3 digits, atomic via document_sequences — never a COUNT).
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

        // Prefer the admission date year (the snapshot) so the ID never changes
        // when the student is promoted.
        $year = $student->admission_date?->year
            ?? $session?->start_date?->year
            ?? (int) (preg_match('/\d{4}/', (string) $session?->name, $m) ? $m[0] : now()->year);

        $classCode = $this->classCode($class);
        $yy = self::twoDigitYear($year);

        return DB::transaction(function () use ($student, $yy, $classCode) {
            $key = "studentid:{$yy}:{$classCode}";

            DB::table('document_sequences')->insertOrIgnore([
                'key' => $key,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('document_sequences')->where('key', $key)->lockForUpdate()->first();

            // The sequence may lag behind IDs that were backfilled or inserted
            // outside this service, so always start after the highest serial
            // already issued for this prefix. This prevents duplicate IDs.
            $floor = $this->highestIssuedSerial($yy.$classCode);
            $serial = max((int) $sequence->last_number, $floor) + 1;

            if ($serial > 999) {
                throw ValidationException::withMessages([
                    'student_id' => 'The admission serial for this year and class has reached its limit (999).',
                ]);
            }

            DB::table('document_sequences')->where('key', $key)->update([
                'last_number' => $serial,
                'updated_at' => now(),
            ]);

            $code = $yy . $classCode . str_pad((string) $serial, 3, '0', STR_PAD_LEFT);

            // Belt-and-braces uniqueness check (sequence locking already prevents dupes).
            $taken = Student::withTrashed()
                ->where('student_code', $code)
                ->when($student->exists, fn ($q) => $q->whereKeyNot($student->getKey()))
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'student_id' => 'Student ID collision detected. Please retry the admission.',
                ]);
            }

            $student->forceFill(['student_code' => $code])->save();

            return $code;
        });
    }

    /**
     * Highest 3-digit serial already used for a YY+CLASS_CODE prefix.
     */
    public function highestIssuedSerial(string $prefix): int
    {
        $max = 0;

        foreach (Student::withTrashed()->where('student_code', 'like', $prefix.'%')->pluck('student_code') as $existing) {
            if (preg_match('/^'.$prefix.'(\d{3})$/', (string) $existing, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max;
    }

    public function classCode(?SchoolClass $class): string
    {
        if (! $class) {
            return '00';
        }

        $level = (int) $class->numeric_level;
        $code = $level + 1;

        if ($code < 1 || $code > 99) {
            throw ValidationException::withMessages([
                'class_id' => "Class {$class->name} has an unusable numeric level ({$level}). The generated class code must fit in two digits (01-99).",
            ]);
        }

        $duplicate = SchoolClass::query()
            ->where('numeric_level', $level)
            ->whereKeyNot($class->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'class_id' => "Another class already uses numeric level {$level}. Fix the class levels before admitting students.",
            ]);
        }

        return str_pad((string) $code, 2, '0', STR_PAD_LEFT);
    }

    public static function twoDigitYear(int $year): string
    {
        return str_pad((string) ($year % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @deprecated Kept for internal callers that only need the numeric level
     * (none should call this for ID generation anymore).
     */
    public function classNumber(?SchoolClass $class): int
    {
        return $class ? (int) $class->numeric_level : 0;
    }
}
