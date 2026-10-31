<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('admission_no', 40)->unique();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->enum('gender', ['male', 'female', 'other'])->index();
            $table->date('date_of_birth');
            $table->string('blood_group', 10)->nullable();
            $table->string('religion', 40)->nullable();
            $table->string('nationality', 60)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('photo_path')->nullable();
            $table->date('admission_date');
            $table->string('previous_school')->nullable();
            $table->text('medical_notes')->nullable();
            $table->enum('status', ['active', 'inactive', 'passed_out', 'transferred', 'archived'])
                ->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
            $table->index('admission_date');
        });

        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('relation', 40);
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('occupation', 80)->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['student_id', 'is_primary']);
        });

        Schema::create('admission_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('enquiry_no', 40)->unique();
            $table->string('student_name', 120);
            $table->string('guardian_name', 120);
            $table->string('phone', 30)->index();
            $table->string('email')->nullable();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->date('follow_up_date')->nullable()->index();
            $table->string('source', 60)->nullable();
            $table->enum('status', ['new', 'contacted', 'follow_up', 'admitted', 'closed'])->default('new')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_enquiries');
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('students');
    }
};
