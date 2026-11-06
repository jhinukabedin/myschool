<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('students', 'sibling_student_id')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('sibling_student_id')->nullable()->after('has_sibling')->constrained('students')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('students', 'sibling_student_id')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropConstrainedForeignId('sibling_student_id');
            });
        }
    }
};
