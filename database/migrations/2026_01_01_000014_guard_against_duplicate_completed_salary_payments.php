<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The reversal feature replaced the unique index on payroll_id so a payroll
     * can be paid again after a reversal. That removed the database-level guard
     * against two *completed* payments for the same payroll. This generated
     * column restores the guarantee: it equals payroll_id only while a payment
     * is completed, and NULL (ignored by unique indexes) once reversed.
     */
    public function up(): void
    {
        Schema::table('salary_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('completed_payroll_id')
                ->nullable()
                ->storedAs("if(`status` = 'completed', `payroll_id`, null)")
                ->after('reversed_at');
        });

        Schema::table('salary_payments', function (Blueprint $table) {
            $table->unique('completed_payroll_id');
        });
    }

    public function down(): void
    {
        Schema::table('salary_payments', function (Blueprint $table) {
            $table->dropUnique(['completed_payroll_id']);
            $table->dropColumn('completed_payroll_id');
        });
    }
};
