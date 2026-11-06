<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('holidays')) {
            return;
        }

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->enum('type', ['public_holiday', 'vacation', 'event', 'exam', 'other'])->default('public_holiday');
            $table->date('start_date')->index();
            $table->date('end_date');
            // all | students | staff
            $table->enum('applies_to', ['all', 'students', 'staff'])->default('all');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
