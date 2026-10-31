<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payrolls MODIFY status ENUM('pending','processed','partially_paid','paid','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Preserve the rows by returning cancelled payrolls to the closest
        // active state before the enum no longer contains the value.
        DB::statement("UPDATE payrolls SET status = 'processed' WHERE status = 'cancelled'");
        DB::statement("ALTER TABLE payrolls MODIFY status ENUM('pending','processed','partially_paid','paid') NOT NULL DEFAULT 'pending'");
    }
};
