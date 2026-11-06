<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fee_structure_items', 'scholarship_value')) {
            return;
        }

        Schema::table('fee_structure_items', function (Blueprint $table) {
            // Scholarship template per fee: how much % or fixed amount is waived for this fee.
            $table->enum('scholarship_type', ['percent', 'fixed'])->default('fixed')->after('discount_amount');
            $table->decimal('scholarship_value', 12, 2)->default(0)->after('scholarship_type');
        });
    }

    public function down(): void
    {
        Schema::table('fee_structure_items', function (Blueprint $table) {
            $table->dropColumn(['scholarship_type', 'scholarship_value']);
        });
    }
};
