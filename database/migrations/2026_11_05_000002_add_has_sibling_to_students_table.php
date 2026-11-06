<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The sibling flag sits next to the Bangla name column, so this migration is
     * ordered after 2026_11_05_000001_add_name_bn_to_students_table.
     */
    public function up(): void
    {
        if (Schema::hasColumn('students', 'has_sibling')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $column = $table->boolean('has_sibling')->default(false);

            // Only anchor the column when the Bangla name column is already present.
            if (Schema::hasColumn('students', 'name_bn')) {
                $column->after('name_bn');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('students', 'has_sibling')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('has_sibling');
            });
        }
    }
};
