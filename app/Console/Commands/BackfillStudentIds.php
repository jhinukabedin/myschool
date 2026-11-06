<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\StudentCodeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillStudentIds extends Command
{
    protected $signature = 'students:backfill-ids {--dry-run : Report what would change without writing}';

    protected $description = 'Assign permanent Student IDs (YY+CLASS_CODE+SERIAL) to students that do not have one yet';

    public function handle(StudentCodeService $codes): int
    {
        $students = Student::query()
            ->where(function ($q) {
                $q->whereNull('student_code')->orWhere('student_code', '');
            })
            ->orderBy('admission_date')
            ->orderBy('id')
            ->get();

        $dry = (bool) $this->option('dry-run');

        if ($students->isEmpty()) {
            $this->info('Every student already has a permanent Student ID.');
        }

        $done = 0;
        $skipped = [];

        foreach ($students as $student) {
            $enrollment = $student->enrollments()
                ->with('class')
                ->join('academic_sessions', 'academic_sessions.id', '=', 'enrollments.academic_session_id')
                ->orderBy('academic_sessions.start_date')
                ->orderBy('enrollments.id')
                ->select('enrollments.*')
                ->first();

            if (! $enrollment || ! $enrollment->class) {
                $skipped[] = $student->id;
                continue;
            }

            // Original admission year/class, never the current (post-promotion) one.
            $year = $student->admission_date?->year
                ?? $enrollment->academicSession?->start_date?->year
                ?? (int) date('Y', strtotime((string) $enrollment->academic_session_id));

            $classCode = (int) $enrollment->class->numeric_level + 1;

            if ($classCode < 1 || $classCode > 99) {
                $skipped[] = $student->id;
                continue;
            }

            $prefix = str_pad((string) ($year % 100), 2, '0', STR_PAD_LEFT)
                .str_pad((string) $classCode, 2, '0', STR_PAD_LEFT);

            // Continue after the highest serial already issued for this prefix.
            $maxUsed = 0;
            foreach (Student::withTrashed()->where('student_code', 'like', $prefix.'%')->pluck('student_code') as $existing) {
                if (preg_match('/^'.$prefix.'(\d{3})$/', (string) $existing, $m)) {
                    $maxUsed = max($maxUsed, (int) $m[1]);
                }
            }

            $serial = $maxUsed + 1;
            do {
                $code = $prefix.str_pad((string) $serial, 3, '0', STR_PAD_LEFT);
                $taken = Student::withTrashed()->where('student_code', $code)->exists();
                if ($taken) {
                    $serial++;
                }
            } while ($taken);

            if ($serial > 999) {
                $skipped[] = $student->id;
                continue;
            }

            $this->line(($dry ? '[dry] ' : '')."Student #{$student->id} {$student->full_name} → {$code}");

            if (! $dry) {
                DB::table('students')->where('id', $student->id)->update(['student_code' => $code]);
            }

            $done++;
        }

        $this->newLine();
        $this->info(($dry ? 'Would assign ' : 'Assigned ')."{$done} Student ID(s).");

        if ($skipped !== []) {
            $this->warn('Skipped (no enrolment or unusable class level): '.implode(', ', $skipped));
        }

        // Point student portal usernames at the permanent Student ID, without
        // touching passwords or guardian accounts. Safe when the target
        // username is free (or already belongs to the same user).
        $synced = 0;
        $links = DB::table('student_portal_links')
            ->join('students', 'students.id', '=', 'student_portal_links.student_id')
            ->join('users', 'users.id', '=', 'student_portal_links.user_id')
            ->whereNotNull('students.student_code')
            ->where('students.student_code', '!=', '')
            ->select('users.id as user_id', 'users.username', 'students.student_code')
            ->get();

        foreach ($links as $link) {
            if ((string) $link->username === (string) $link->student_code) {
                continue;
            }

            $taken = DB::table('users')
                ->whereRaw('LOWER(username) = ?', [mb_strtolower((string) $link->student_code)])
                ->where('id', '!=', $link->user_id)
                ->exists();

            if ($taken) {
                $this->warn("Username {$link->student_code} already in use; skipped user #{$link->user_id}.");
                continue;
            }

            $this->line(($dry ? '[dry] ' : '')."User #{$link->user_id}: username '{$link->username}' → '{$link->student_code}'");

            if (! $dry) {
                DB::table('users')->where('id', $link->user_id)->update(['username' => $link->student_code]);
            }

            $synced++;
        }

        $this->info(($dry ? 'Would sync ' : 'Synced ')."{$synced} student portal username(s).");

        return self::SUCCESS;
    }
}
