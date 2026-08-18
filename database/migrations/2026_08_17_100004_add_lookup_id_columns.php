<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable for now — the next migration backfills them from the
        // still-present text columns before the final migration makes them
        // required and drops the old text columns.
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('category')->constrained('categories');
            $table->foreignId('priority_id')->nullable()->after('priority')->constrained('priorities');
            $table->foreignId('status_id')->nullable()->after('status')->constrained('statuses');
        });

        Schema::table('ticket_status_histories', function (Blueprint $table) {
            $table->foreignId('old_status_id')->nullable()->after('old_status')->constrained('statuses');
            $table->foreignId('new_status_id')->nullable()->after('new_status')->constrained('statuses');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('type_id')->nullable()->after('type')->constrained('event_types');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('priority_id');
            $table->dropConstrainedForeignId('status_id');
        });

        Schema::table('ticket_status_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('old_status_id');
            $table->dropConstrainedForeignId('new_status_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('type_id');
        });
    }
};
