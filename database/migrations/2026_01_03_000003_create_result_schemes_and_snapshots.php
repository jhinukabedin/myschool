<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('exam_type', 60)->nullable()->index();
            $table->foreignId('academic_session_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->unique(['name', 'academic_session_id'], 'grading_schemes_name_session_unique');
        });

        Schema::table('grade_rules', function (Blueprint $table) {
            $table->foreignId('grading_scheme_id')->nullable()->after('id')
                ->constrained('grading_schemes')->cascadeOnDelete();
        });

        Schema::table('grade_rules', function (Blueprint $table) {
            $table->dropUnique('grade_rules_min_percent_max_percent_unique');
            $table->unique(
                ['grading_scheme_id', 'min_percent', 'max_percent'],
                'grade_rules_scheme_range_unique'
            );
        });

        Schema::table('examinations', function (Blueprint $table) {
            $table->foreignId('grading_scheme_id')->nullable()->after('exam_type')
                ->constrained('grading_schemes')->nullOnDelete();
            $table->dateTime('result_published_at')->nullable();
            $table->dateTime('result_locked_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('result_note', 500)->nullable();
        });

        Schema::table('marks', function (Blueprint $table) {
            $table->string('special_status', 30)->nullable()->index();
            $table->string('special_note', 500)->nullable();
        });

        Schema::create('result_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mark_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_marks', 6, 2)->nullable();
            $table->decimal('new_marks', 6, 2)->nullable();
            $table->string('old_grade', 10)->nullable();
            $table->string('new_grade', 10)->nullable();
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            $table->text('reason');
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['mark_id', 'created_at'], 'result_corrections_mark_created_index');
        });

        Schema::create('result_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grading_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->json('grading_rules');
            $table->json('result_data');
            $table->decimal('total_marks', 8, 2)->default(0);
            $table->decimal('gpa', 4, 2)->default(0);
            $table->string('grade', 10)->nullable();
            $table->string('result_status', 30)->default('Pass');
            $table->dateTime('published_at');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['examination_id', 'student_id'], 'result_snapshots_exam_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_snapshots');
        Schema::dropIfExists('result_corrections');

        Schema::table('marks', function (Blueprint $table) {
            $table->dropColumn(['special_status', 'special_note']);
        });

        Schema::table('examinations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grading_scheme_id');
            $table->dropConstrainedForeignId('published_by');
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn(['result_published_at', 'result_locked_at', 'result_note']);
        });

        Schema::table('grade_rules', function (Blueprint $table) {
            $table->dropUnique('grade_rules_scheme_range_unique');
            $table->dropConstrainedForeignId('grading_scheme_id');
            $table->unique(['min_percent', 'max_percent']);
        });

        Schema::dropIfExists('grading_schemes');
    }
};
