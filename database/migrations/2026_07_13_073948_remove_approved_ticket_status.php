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
        DB::statement("ALTER TABLE tickets MODIFY status ENUM('Open', 'Pending', 'In Progress', 'Rejected', 'Resolved') NOT NULL DEFAULT 'Open'");

        // Historical rows can reference 'Approved' even though no ticket is
        // currently in that status — fold that history into 'Resolved' before
        // the enum stops accepting the old value.
        DB::table('ticket_status_histories')->where('old_status', 'Approved')->update(['old_status' => 'Resolved']);
        DB::table('ticket_status_histories')->where('new_status', 'Approved')->update(['new_status' => 'Resolved']);

        DB::statement("ALTER TABLE ticket_status_histories MODIFY old_status ENUM('Open', 'Pending', 'In Progress', 'Rejected', 'Resolved') NULL");
        DB::statement("ALTER TABLE ticket_status_histories MODIFY new_status ENUM('Open', 'Pending', 'In Progress', 'Rejected', 'Resolved') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE tickets MODIFY status ENUM('Open', 'Pending', 'In Progress', 'Approved', 'Rejected', 'Resolved') NOT NULL DEFAULT 'Open'");
        DB::statement("ALTER TABLE ticket_status_histories MODIFY old_status ENUM('Open', 'Pending', 'In Progress', 'Approved', 'Rejected', 'Resolved') NULL");
        DB::statement("ALTER TABLE ticket_status_histories MODIFY new_status ENUM('Open', 'Pending', 'In Progress', 'Approved', 'Rejected', 'Resolved') NOT NULL");
    }
};
