<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 80)->nullable()->unique()->after('name');
            $table->string('phone_normalized', 30)->nullable()->index()->after('phone');
        });

        // Portal accounts may authenticate by username, student ID or mobile
        // number and therefore do not always have an email address.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('student_code', 20)->nullable()->unique()->after('admission_no');
        });

        $this->normalizeExistingPhones();
        $this->backfillStudentCodes();
    }

    public function down(): void
    {
        // Restore a non-null email only where possible. Database constraints
        // are intentionally kept broad enough to avoid destroying portal data.
        DB::table('users')->whereNull('email')->update([
            'email' => DB::raw("CONCAT('portal-', id, '@localhost.invalid')"),
        ]);

        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['student_code']);
            $table->dropColumn('student_code');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'phone_normalized']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }

    private function normalizeExistingPhones(): void
    {
        foreach (DB::table('users')->whereNotNull('phone')->get(['id', 'phone']) as $row) {
            DB::table('users')->where('id', $row->id)->update([
                'phone_normalized' => $this->normalizePhone($row->phone),
            ]);
        }
    }

    private function backfillStudentCodes(): void
    {
        $students = DB::table('students')
            ->whereNull('student_code')
            ->orderBy('id')
            ->get(['id']);

        foreach ($students as $row) {
            $enrollment = DB::table('enrollments')
                ->join('academic_sessions', 'academic_sessions.id', '=', 'enrollments.academic_session_id')
                ->join('classes', 'classes.id', '=', 'enrollments.class_id')
                ->where('enrollments.student_id', $row->id)
                ->orderByDesc('academic_sessions.start_date')
                ->orderByDesc('enrollments.id')
                ->first([
                    'academic_sessions.start_date',
                    'academic_sessions.name as session_name',
                    'classes.name as class_name',
                    'classes.numeric_level',
                    'enrollments.roll_no',
                ]);

            if (! $enrollment) {
                continue;
            }

            $year = (int) substr((string) $enrollment->start_date, 0, 4);
            $year = $year ?: (int) (preg_match('/\d{4}/', (string) $enrollment->session_name, $match) ? $match[0] : date('Y'));
            $classCode = preg_match('/(\d+)/', (string) $enrollment->class_name, $match)
                ? (int) $match[1]
                : max(0, (int) $enrollment->numeric_level);
            $sequence = max(1, (int) $enrollment->roll_no);
            $prefix = str_pad((string) $year, 4, '0', STR_PAD_LEFT)
                .str_pad((string) $classCode, 2, '0', STR_PAD_LEFT);

            do {
                $code = $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
                $sequence++;
            } while (DB::table('students')->where('student_code', $code)->exists());

            DB::table('students')->where('id', $row->id)->update(['student_code' => $code]);
        }
    }

    private function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '880') && strlen($digits) > 10) {
            return '0'.substr($digits, 3);
        }

        if (str_starts_with($digits, '1') && strlen($digits) === 10) {
            return '0'.$digits;
        }

        return $digits;
    }
};
