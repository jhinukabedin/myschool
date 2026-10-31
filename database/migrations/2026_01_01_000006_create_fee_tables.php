<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->enum('type', ['monthly', 'annual', 'admission', 'examination', 'transport', 'other'])
                ->default('other')->index();
            $table->boolean('is_optional')->default(false);
            $table->timestamps();
        });

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_session_id', 'class_id']);
        });

        Schema::create('fee_structure_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_category_id')->constrained('fee_categories')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->enum('frequency', ['monthly', 'one_time', 'annual', 'exam'])->default('monthly');
            $table->unsignedTinyInteger('due_day')->default(10);
            $table->boolean('is_optional')->default(false);
            $table->timestamps();

            $table->unique(
                ['fee_structure_id', 'fee_category_id'],
                'fee_items_structure_category_unique'
            );
        });

        Schema::create('student_fee_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_structure_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fee_structure_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fee_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7)->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('waiver_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->date('due_date')->index();
            $table->enum('status', ['pending', 'partial', 'paid', 'waived'])->default('pending')->index();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(
                ['student_id', 'fee_structure_item_id', 'academic_session_id', 'period'],
                'fee_assignments_student_item_period_unique'
            );
            $table->index(['student_id', 'academic_session_id', 'status'], 'fee_assignments_student_session_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_fee_assignments');
        Schema::dropIfExists('fee_structure_items');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_categories');
    }
};
