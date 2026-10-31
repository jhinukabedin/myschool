<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_menu_items', function (Blueprint $table) {
            $table->string('icon', 60)->nullable()->after('title');
            $table->string('subtitle', 160)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('website_menu_items', function (Blueprint $table) {
            $table->dropColumn(['icon', 'subtitle']);
        });
    }
};
