<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep a non-unique index available before dropping the unique index so
        // the payroll foreign key always has a supporting index.
        Schema::table('salary_payments', function (Blueprint $table) {
            $table->index('payroll_id');
        });

        Schema::table('salary_payments', function (Blueprint $table) {
            $table->dropUnique(['payroll_id']);
        });

        Schema::table('salary_payments', function (Blueprint $table) {
            $table->enum('status', ['completed', 'reversed'])->default('completed')->after('paid_by')->index();
            $table->dateTime('reversed_at')->nullable()->after('status');
        });

        Schema::create('salary_payment_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_payment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reversed_at');
            $table->timestamps();

            $table->index('salary_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payment_reversals');

        Schema::table('salary_payments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'reversed_at']);
        });

        Schema::table('salary_payments', function (Blueprint $table) {
            $table->unique('payroll_id');
        });

        Schema::table('salary_payments', function (Blueprint $table) {
            $table->dropIndex(['payroll_id']);
        });
    }
};
