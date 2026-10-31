<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notices can be written as plain text (the original behaviour) or as HTML
     * from the rich text editor. The format decides how the body is stored and
     * rendered on the website and portals.
     */
    public function up(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->string('body_format', 10)->default('text')->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->dropColumn('body_format');
        });
    }
};
