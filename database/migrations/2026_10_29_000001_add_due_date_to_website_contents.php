<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assignments are CMS records managed from the website administration area
     * and shown in the student portal with an optional deadline.
     */
    private const TYPES = [
        'page', 'news', 'event', 'gallery', 'download', 'faculty',
        'slider', 'introduction', 'administration_message', 'principal_message',
        'president_message', 'academic',
        'admission_info', 'routine', 'academic_calendar', 'syllabus', 'video',
        'assignment',
    ];

    public function up(): void
    {
        Schema::table('website_contents', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('published_at');
        });

        if (DB::getDriverName() === 'mysql') {
            $types = implode(',', array_map(fn (string $type) => "'{$type}'", self::TYPES));
            DB::statement("ALTER TABLE website_contents MODIFY type ENUM({$types}) NOT NULL");
        }
        // SQLite stores Laravel enums as a string column, so no table rebuild
        // is needed for the new value in the test database.
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('website_contents')
                ->where('type', 'assignment')
                ->update(['type' => 'page']);

            $types = implode(',', array_map(
                fn (string $type) => "'{$type}'",
                ['page', 'news', 'event', 'gallery', 'download', 'faculty', 'slider', 'introduction', 'administration_message', 'principal_message', 'president_message', 'academic', 'admission_info', 'routine', 'academic_calendar', 'syllabus', 'video']
            ));
            DB::statement("ALTER TABLE website_contents MODIFY type ENUM({$types}) NOT NULL");
        }

        Schema::table('website_contents', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
