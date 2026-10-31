<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow multiple salary installments per payroll.
     *
     * The previous design allowed exactly one completed payment per payroll via a
     * generated unique column. Installments require several completed payments,
     * so that guard is replaced by:
     *   - a row-level lock on the payroll during payment (see PayrollService), and
     *   - an application-level check that the sum of valid installments never
     *     exceeds the net payable amount.
     * A CHECK constraint still guarantees every installment is strictly positive.
     */
    public function up(): void
    {
        Schema::table('salary_payments', function (Blueprint $table) {
            $table->dropUnique(['completed_payroll_id']);
            $table->dropColumn('completed_payroll_id');
        });

        Schema::table('salary_payments', function (Blueprint $table) {
            $table->string('note')->nullable()->after('reference_no');
            $table->index(['payroll_id', 'status'], 'salary_payments_payroll_status_index');
        });

        // MySQL 8.0.16+ enforces CHECK constraints. Positive installments only.
        DB::statement('ALTER TABLE salary_payments ADD CONSTRAINT chk_salary_payments_amount_positive CHECK (amount > 0)');

        // Add the partially paid state to the payroll lifecycle.
        DB::statement("ALTER TABLE payrolls MODIFY status ENUM('pending','processed','partially_paid','paid') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payrolls MODIFY status ENUM('pending','processed','paid') NOT NULL DEFAULT 'pending'");

        DB::statement('ALTER TABLE salary_payments DROP CONSTRAINT chk_salary_payments_amount_positive');

        Schema::table('salary_payments', function (Blueprint $table) {
            $table->dropIndex('salary_payments_payroll_status_index');
            $table->dropColumn('note');
        });

        Schema::table('salary_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('completed_payroll_id')
                ->nullable()
                ->storedAs("if(`status` = 'completed', `payroll_id`, null)");
            $table->unique('completed_payroll_id');
        });
    }
};
