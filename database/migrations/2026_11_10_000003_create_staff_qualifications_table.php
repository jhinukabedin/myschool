<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff_qualifications')) {
            return;
        }

        Schema::create('staff_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('exam_name', 120);
            $table->string('institution', 160)->nullable();
            $table->unsignedSmallInteger('passing_year')->nullable();
            $table->string('result', 60)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['staff_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_qualifications');
    }
};
