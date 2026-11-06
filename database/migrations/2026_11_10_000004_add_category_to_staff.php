<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('staff', 'category')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            $table->enum('category', ['academic', 'non_academic'])->default('academic')->after('designation_id')->index();
        });

        // Classify existing staff from their designation: teaching roles are
        // academic, everything else is non-academic.
        $academicDesignationIds = DB::table('designations')
            ->where(function ($query) {
                $query->where('name', 'like', '%Teacher%')
                    ->orWhere('name', 'like', '%Principal%')
                    ->orWhere('name', 'like', '%Professor%')
                    ->orWhere('name', 'like', '%Lecturer%');
            })
            ->pluck('id')
            ->all();

        if ($academicDesignationIds !== []) {
            DB::table('staff')
                ->whereNotNull('designation_id')
                ->whereNotIn('designation_id', $academicDesignationIds)
                ->update(['category' => 'non_academic']);
        } else {
            DB::table('staff')->whereNotNull('designation_id')->update(['category' => 'non_academic']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('staff', 'category')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
