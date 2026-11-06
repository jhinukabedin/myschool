<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Point the existing public "ফলাফল" menu item at the new result search
        // page instead of the student login screen. Only the original seeded
        // value is touched, so custom administrator links stay intact.
        DB::table('website_menu_items')
            ->where('title', 'ফলাফল')
            ->where(function ($query) {
                $query->where('url', '/student/login')
                    ->orWhere('route_name', 'student.login');
            })
            ->update([
                'url' => null,
                'route_name' => 'website.results',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('website_menu_items')
            ->where('title', 'ফলাফল')
            ->where('route_name', 'website.results')
            ->update([
                'url' => '/student/login',
                'route_name' => null,
                'updated_at' => now(),
            ]);
    }
};
