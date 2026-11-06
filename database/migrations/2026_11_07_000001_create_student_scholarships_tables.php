<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_scholarships')) {
            return;
        }

        Schema::create('student_scholarships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->string('scholarship_name', 120);
            $table->string('scholarship_type', 60)->default('merit')->index();
            $table->enum('discount_method', ['percent', 'fixed'])->default('percent');
            $table->decimal('discount_value', 12, 2)->default(0);
            // all | selected — whether every fee category is covered
            $table->enum('applies_to', ['all', 'selected'])->default('all');
            // once | monthly | per_invoice | academic_year
            $table->enum('discount_frequency', ['once', 'monthly', 'per_invoice', 'academic_year'])->default('monthly');
            $table->decimal('maximum_discount_amount', 12, 2)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'pending', 'approved', 'active', 'suspended', 'expired', 'rejected'])->default('pending')->index();
            $table->text('description')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'academic_session_id', 'status']);
        });

        Schema::create('student_scholarship_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_scholarship_id')->constrained('student_scholarships')->cascadeOnDelete();
            $table->foreignId('fee_category_id')->constrained('fee_categories')->restrictOnDelete();
            $table->enum('discount_type', ['percent', 'fixed'])->default('percent');
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->enum('discount_frequency', ['once', 'monthly', 'per_invoice', 'academic_year'])->default('monthly');
            $table->decimal('maximum_discount_amount', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['student_scholarship_id', 'fee_category_id'], 'scholarship_category_unique');
        });

        Schema::create('scholarship_invoice_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_scholarship_id')->nullable()->constrained('student_scholarships')->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_fee_assignment_id')->constrained('student_fee_assignments', 'id')->cascadeOnDelete()->index(false);
            $table->decimal('original_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('payable_amount', 12, 2);
            $table->string('adjustment_reason', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_fee_assignment_id'], 'assignment_adjustment_unique');
            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarship_invoice_adjustments');
        Schema::dropIfExists('student_scholarship_items');
        Schema::dropIfExists('student_scholarships');
    }
};
