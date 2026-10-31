<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['academic_session_id', 'class_id', 'section_id', 'subject_id', 'staff_id'],
                'teacher_assignments_unique'
            );
            $table->index(['staff_id', 'academic_session_id'], 'teacher_assignments_staff_session_index');
            $table->index(['class_id', 'section_id', 'subject_id'], 'teacher_assignments_class_subject_index');
        });

        // Marks workflow metadata: who entered / submitted / approved and when.
        Schema::table('marks', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted', 'approved'])->default('draft')->index();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['status', 'submitted_at', 'approved_at']);
        });

        Schema::dropIfExists('teacher_assignments');
    }
};
