<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove monthly fee rows that were generated for months BEFORE the student
     * was admitted. Older versions of FeeAssignmentService started the monthly
     * series at the session/structure start, so a student admitted in August
     * received January–July tuition rows and their outstanding balance looked
     * far larger than reality.
     *
     * Safety guards — a row is only removed when ALL of these are true:
     *   - it is a monthly row (period is set),
     *   - its period is earlier than the student's admission month,
     *   - it was generated from a fee structure item,
     *   - it has NO payment allocation of any kind (nothing to reverse),
     *   - and no scholarship invoice adjustment references it.
     *
     * Nothing that has ever been paid is touched.
     */
    public function up(): void
    {
        $candidates = DB::table('student_fee_assignments as a')
            ->join('students as s', 's.id', '=', 'a.student_id')
            ->whereNotNull('a.period')
            ->whereNotNull('a.fee_structure_item_id')
            ->whereNotNull('s.admission_date')
            ->whereRaw("a.period < DATE_FORMAT(s.admission_date, '%Y-%m')")
            ->pluck('a.id');

        if ($candidates->isEmpty()) {
            return;
        }

        $hasPayments = DB::table('payment_allocations')
            ->whereIn('student_fee_assignment_id', $candidates)
            ->distinct()
            ->pluck('student_fee_assignment_id');

        $hasAdjustments = DB::table('scholarship_invoice_adjustments')
            ->whereIn('student_fee_assignment_id', $candidates)
            ->distinct()
            ->pluck('student_fee_assignment_id');

        $safe = $candidates->diff($hasPayments)->diff($hasAdjustments)->values();

        if ($safe->isEmpty()) {
            return;
        }

        $removed = 0;

        foreach ($safe->chunk(200) as $chunk) {
            $removed += DB::table('student_fee_assignments')->whereIn('id', $chunk)->delete();
        }

        logger()->info("Removed {$removed} pre-admission fee rows (generated before the student joined).");

        // Keep the per-year/class Student ID sequences and nothing else in sync —
        // no other tables are affected by this cleanup.
    }

    public function down(): void
    {
        // Data correction only; incorrectly generated rows are not restored.
    }
};
