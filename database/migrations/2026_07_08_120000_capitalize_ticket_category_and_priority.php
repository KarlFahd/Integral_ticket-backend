<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL enum labels are case-insensitive, so 'hardware' and 'Hardware' can't
        // coexist in the same enum definition. Go through a plain VARCHAR column
        // while converting existing rows, then lock it back down to the new enum.
        DB::statement('ALTER TABLE tickets MODIFY category VARCHAR(20) DEFAULT "software"');
        DB::statement('ALTER TABLE tickets MODIFY priority VARCHAR(20) DEFAULT "low"');

        DB::table('tickets')->update(['category' => DB::raw("CONCAT(UPPER(LEFT(category, 1)), SUBSTRING(category, 2))")]);
        DB::table('tickets')->update(['priority' => DB::raw("CONCAT(UPPER(LEFT(priority, 1)), SUBSTRING(priority, 2))")]);

        DB::statement("ALTER TABLE tickets MODIFY category ENUM('Hardware','Software','Network','Account') DEFAULT 'Software'");
        DB::statement("ALTER TABLE tickets MODIFY priority ENUM('Low','Medium','High') DEFAULT 'Low'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE tickets MODIFY category VARCHAR(20) DEFAULT "Software"');
        DB::statement('ALTER TABLE tickets MODIFY priority VARCHAR(20) DEFAULT "Low"');

        DB::table('tickets')->update(['category' => DB::raw('LOWER(category)')]);
        DB::table('tickets')->update(['priority' => DB::raw('LOWER(priority)')]);

        DB::statement("ALTER TABLE tickets MODIFY category ENUM('hardware','software','network','account') DEFAULT 'software'");
        DB::statement("ALTER TABLE tickets MODIFY priority ENUM('low','medium','high') DEFAULT 'low'");
    }
};
