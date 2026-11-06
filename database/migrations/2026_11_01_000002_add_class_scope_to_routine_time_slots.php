<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_time_slots', function (Blueprint $table) {
            $table->foreignId('class_id')->nullable()->after('id')->constrained('classes')->cascadeOnDelete();
            $table->index(['class_id', 'sort_order', 'start_time'], 'routine_slots_class_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('routine_time_slots', function (Blueprint $table) {
            $table->dropIndex('routine_slots_class_order_index');
            $table->dropConstrainedForeignId('class_id');
        });
    }
};
