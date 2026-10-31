<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_time_slots', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->time('start_time');
            $table->time('end_time');
            $table->string('type', 20)->default('class')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['start_time', 'end_time'], 'routine_time_slots_time_index');
        });

        Schema::create('class_routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['academic_session_id', 'class_id', 'section_id'],
                'class_routines_context_unique'
            );
            $table->index(['class_id', 'section_id'], 'class_routines_class_section_index');
        });

        Schema::create('routine_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_routine_id')->constrained('class_routines')->cascadeOnDelete();
            $table->string('day', 16)->index();
            $table->foreignId('routine_time_slot_id')->constrained('routine_time_slots')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->string('classroom', 80)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['class_routine_id', 'day', 'routine_time_slot_id'],
                'routine_entries_cell_unique'
            );
            $table->index(['staff_id', 'day'], 'routine_entries_teacher_day_index');
            $table->index(['classroom', 'day'], 'routine_entries_room_day_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_entries');
        Schema::dropIfExists('class_routines');
        Schema::dropIfExists('routine_time_slots');
    }
};
