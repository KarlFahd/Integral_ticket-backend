<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Maps every existing ticket/event's text value to the matching new
     * lookup-table id, so no existing data is lost when the text columns
     * are dropped in the next migration. Names match exactly (both sides
     * already share the same capitalization), so a plain join is enough.
     */
    public function up(): void
    {
        DB::statement('UPDATE tickets t JOIN categories c ON c.name = t.category SET t.category_id = c.id');
        DB::statement('UPDATE tickets t JOIN priorities p ON p.name = t.priority SET t.priority_id = p.id');
        DB::statement('UPDATE tickets t JOIN statuses s ON s.name = t.status SET t.status_id = s.id');

        DB::statement('UPDATE ticket_status_histories h JOIN statuses s ON s.name = h.old_status SET h.old_status_id = s.id');
        DB::statement('UPDATE ticket_status_histories h JOIN statuses s ON s.name = h.new_status SET h.new_status_id = s.id');

        DB::statement('UPDATE events e JOIN event_types et ON et.name = e.type SET e.type_id = et.id');
    }

    public function down(): void
    {
        // Nothing to reverse — the text columns (source of truth for this
        // backfill) are untouched by this migration.
    }
};
