<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->unsignedInteger('roll_no');
            $table->enum('status', ['active', 'promoted', 'completed', 'transferred', 'inactive'])
                ->default('active')->index();
            $table->foreignId('promoted_from_enrollment_id')->nullable()
                ->constrained('enrollments')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_session_id'], 'enrollments_student_session_unique');
            $table->unique(
                ['academic_session_id', 'class_id', 'section_id', 'roll_no'],
                'enrollments_session_class_section_roll_unique'
            );
            $table->index(['academic_session_id', 'class_id', 'section_id']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->enum('type', ['theory', 'practical', 'both'])->default('theory');
            $table->decimal('full_marks', 6, 2)->default(100);
            $table->decimal('pass_marks', 6, 2)->default(33);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['academic_session_id', 'class_id', 'subject_id'],
                'class_subjects_session_class_subject_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_subjects');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('enrollments');
    }
};
