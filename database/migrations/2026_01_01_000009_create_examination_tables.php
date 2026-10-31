<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('exam_type', 60)->default('term');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'published', 'completed'])->default('draft')->index();
            $table->timestamps();

            $table->unique(['academic_session_id', 'name'], 'exams_session_name_unique');
        });

        Schema::create('examination_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->decimal('full_marks', 6, 2);
            $table->decimal('pass_marks', 6, 2);
            $table->date('exam_date')->nullable();
            $table->time('start_time')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->timestamps();

            $table->unique(
                ['examination_id', 'class_id', 'subject_id'],
                'exam_subjects_exam_class_subject_unique'
            );
        });

        Schema::create('grade_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->decimal('min_percent', 5, 2);
            $table->decimal('max_percent', 5, 2);
            $table->string('grade', 10);
            $table->decimal('grade_point', 4, 2);
            $table->boolean('is_fail')->default(false);
            $table->timestamps();

            $table->unique(['min_percent', 'max_percent']);
        });

        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('marks_obtained', 6, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('grade', 10)->nullable();
            $table->decimal('grade_point', 4, 2)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['examination_subject_id', 'student_id'],
                'marks_exam_subject_student_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marks');
        Schema::dropIfExists('grade_rules');
        Schema::dropIfExists('examination_subjects');
        Schema::dropIfExists('examinations');
    }
};
