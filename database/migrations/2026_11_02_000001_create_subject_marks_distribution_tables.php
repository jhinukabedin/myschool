<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_mark_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('full_marks', 6, 2);
            $table->decimal('pass_marks', 6, 2);
            $table->json('components')->nullable();
            $table->timestamps();
        });

        Schema::create('class_subject_mark_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->decimal('full_marks', 6, 2);
            $table->decimal('pass_marks', 6, 2);
            $table->json('components')->nullable();
            $table->timestamps();
            $table->unique(['class_id', 'subject_id'], 'class_subject_marks_override_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_subject_mark_overrides');
        Schema::dropIfExists('subject_mark_defaults');
    }
};
