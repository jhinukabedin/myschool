<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Content types added for the standalone website managers: admission info,
     * class routine, academic calendar, syllabus and video gallery.
     */
    private const TYPES = [
        'page', 'news', 'event', 'gallery', 'download', 'faculty',
        'slider', 'introduction', 'administration_message', 'principal_message',
        'president_message', 'academic',
        'admission_info', 'routine', 'academic_calendar', 'syllabus', 'video',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $types = implode(',', array_map(fn (string $type) => "'{$type}'", self::TYPES));
            DB::statement("ALTER TABLE website_contents MODIFY type ENUM({$types}) NOT NULL");
        }
        // SQLite stores Laravel enums as a string column, so no table rebuild
        // is needed for the new values in the test database.
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('website_contents')
                ->whereIn('type', ['admission_info', 'routine', 'academic_calendar', 'syllabus', 'video'])
                ->update(['type' => 'page']);

            $types = implode(',', array_map(
                fn (string $type) => "'{$type}'",
                ['page', 'news', 'event', 'gallery', 'download', 'faculty', 'slider', 'introduction', 'administration_message', 'principal_message', 'president_message', 'academic']
            ));
            DB::statement("ALTER TABLE website_contents MODIFY type ENUM({$types}) NOT NULL");
        }
    }
};
