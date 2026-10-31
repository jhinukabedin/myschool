<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examination_subjects', function (Blueprint $table) {
            $table->json('components')->nullable()->after('pass_marks');
        });

        Schema::table('marks', function (Blueprint $table) {
            $table->json('component_marks')->nullable()->after('marks_obtained');
        });
    }

    public function down(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->dropColumn('component_marks');
        });

        Schema::table('examination_subjects', function (Blueprint $table) {
            $table->dropColumn('components');
        });
    }
};
