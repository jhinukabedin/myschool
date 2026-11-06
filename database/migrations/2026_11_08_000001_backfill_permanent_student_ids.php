<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill permanent Student IDs (YY + CLASS_CODE + SERIAL) for students that
     * do not have one yet. Existing IDs are never touched or regenerated.
     *
     * The admission year comes from the student's admission_date, and the class
     * code from the numeric_level of the class in their EARLIEST enrolment — the
     * original admission class, not the current one after promotion.
     */
    public function up(): void
    {
        $students = DB::table('students')
            ->where(function ($q) {
                $q->whereNull('student_code')->orWhere('student_code', '');
            })
            ->orderBy('admission_date')
            ->orderBy('id')
            ->get(['id', 'admission_date']);

        // Highest serial already used for each YY+CLASS_CODE prefix.
        $used = [];
        foreach (DB::table('students')->whereNotNull('student_code')->get(['student_code']) as $row) {
            $code = (string) $row->student_code;
            if (preg_match('/^(\d{5})(\d{3})$/', $code, $m)) {
                $prefix = $m[1];
                $used[$prefix] = max($used[$prefix] ?? 0, (int) $m[2]);
            }
        }

        $unresolved = [];

        foreach ($students as $student) {
            $enrollment = DB::table('enrollments')
                ->join('academic_sessions', 'academic_sessions.id', '=', 'enrollments.academic_session_id')
                ->join('classes', 'classes.id', '=', 'enrollments.class_id')
                ->where('enrollments.student_id', $student->id)
                ->orderBy('academic_sessions.start_date')
                ->orderBy('enrollments.id')
                ->first(['academic_sessions.start_date', 'academic_sessions.name as session_name', 'classes.numeric_level']);

            if (! $enrollment) {
                $unresolved[] = $student->id;
                continue;
            }

            $year = (int) substr((string) $student->admission_date, 0, 4);
            if ($year <= 0) {
                $year = (int) substr((string) $enrollment->start_date, 0, 4);
            }
            if ($year <= 0) {
                $year = (int) (preg_match('/\d{4}/', (string) $enrollment->session_name, $m) ? $m[0] : date('Y'));
            }

            $classCode = (int) $enrollment->numeric_level + 1;
            if ($classCode < 1 || $classCode > 99) {
                $unresolved[] = $student->id;
                continue;
            }

            $prefix = str_pad((string) ($year % 100), 2, '0', STR_PAD_LEFT)
                .str_pad((string) $classCode, 2, '0', STR_PAD_LEFT);

            $serial = ($used[$prefix] ?? 0) + 1;

            // Skip any code that already exists (defensive; prefix counter should prevent it).
            do {
                $code = $prefix.str_pad((string) $serial, 3, '0', STR_PAD_LEFT);
                $taken = DB::table('students')->where('student_code', $code)->exists();
                if ($taken) {
                    $serial++;
                }
            } while ($taken);

            if ($serial > 999) {
                $unresolved[] = $student->id;
                continue;
            }

            DB::table('students')->where('id', $student->id)->update(['student_code' => $code]);
            $used[$prefix] = $serial;
        }

        if ($unresolved !== []) {
            // Reported but never guessed: these need a manual class/year decision.
            logger()->warning('Student ID backfill could not resolve: '.implode(', ', $unresolved));
        }
    }

    public function down(): void
    {
        // Student IDs are permanent; never strip them on rollback.
    }
};
