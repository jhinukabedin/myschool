<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('student_fee_assignments', 'scholarship_amount')) {
            return;
        }

        Schema::table('student_fee_assignments', function (Blueprint $table) {
            $table->enum('scholarship_type', ['percent', 'fixed'])->nullable()->after('discount_amount');
            $table->decimal('scholarship_value', 12, 2)->default(0)->after('scholarship_type');
            $table->decimal('scholarship_amount', 12, 2)->default(0)->after('scholarship_value');
        });
    }

    public function down(): void
    {
        Schema::table('student_fee_assignments', function (Blueprint $table) {
            $table->dropColumn(['scholarship_type', 'scholarship_value', 'scholarship_amount']);
        });
    }
};
