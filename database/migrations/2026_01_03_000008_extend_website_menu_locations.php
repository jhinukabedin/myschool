<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The menu builder originally supported header, footer and secondary locations.
 * The dynamic header/footer/hero management adds login buttons, dedicated footer
 * columns, the hero featured menu and the website sidebar. Extending the enum is
 * the smallest change that keeps the existing menu system and its unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE website_menus MODIFY location ENUM(
            'header', 'footer', 'secondary', 'login', 'footer_institution', 'footer_links', 'featured', 'sidebar'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE website_menus MODIFY location ENUM('header', 'footer', 'secondary') NOT NULL");
    }
};