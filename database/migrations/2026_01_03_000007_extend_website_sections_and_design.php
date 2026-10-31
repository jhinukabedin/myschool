<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPES = [
        'page', 'news', 'event', 'gallery', 'download', 'faculty',
        'slider', 'introduction', 'administration_message', 'principal_message',
        'president_message', 'academic',
    ];

    public function up(): void
    {
        Schema::table('website_contents', function (Blueprint $table) {
            $table->string('link_url', 500)->nullable()->after('file_path');
            $table->string('button_label', 80)->nullable()->after('link_url');
        });

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
                ->whereIn('type', ['slider', 'introduction', 'administration_message', 'principal_message', 'president_message', 'academic'])
                ->update(['type' => 'page']);

            DB::statement("ALTER TABLE website_contents MODIFY type ENUM('page','news','event','gallery','download','faculty') NOT NULL");
        }

        Schema::table('website_contents', function (Blueprint $table) {
            $table->dropColumn(['link_url', 'button_label']);
        });
    }
};
