<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_attendances')) {
            Schema::create('staff_attendances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
                $table->date('attendance_date')->index();
                $table->enum('status', ['present', 'absent', 'late', 'half_day', 'leave'])->default('present');
                $table->string('note', 255)->nullable();
                $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['staff_id', 'attendance_date'], 'staff_attendance_unique');
            });
        }

        if (! Schema::hasTable('staff_leaves')) {
            Schema::create('staff_leaves', function (Blueprint $table) {
                $table->id();
                $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
                $table->enum('leave_type', ['casual', 'sick', 'annual', 'maternity', 'unpaid', 'other'])->default('casual');
                $table->date('start_date')->index();
                $table->date('end_date');
                $table->unsignedSmallInteger('days')->default(1);
                $table->text('reason');
                $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending')->index();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->string('decision_note', 255)->nullable();
                $table->timestamps();

                $table->index(['staff_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leaves');
        Schema::dropIfExists('staff_attendances');
    }
};
