<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false)->index();
            $table->enum('status', ['active', 'closed'])->default('active')->index();
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedSmallInteger('numeric_level')->default(0)->index();
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('class_section', function (Blueprint $table) {
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->primary(['class_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_section');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('academic_sessions');
    }
};
