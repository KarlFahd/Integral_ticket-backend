<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['category', 'priority', 'status']);
        });

        Schema::table('ticket_status_histories', function (Blueprint $table) {
            $table->dropColumn(['old_status', 'new_status']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable(false)->change();
            $table->foreignId('priority_id')->nullable(false)->change();
            $table->foreignId('status_id')->nullable(false)->change();
        });

        Schema::table('ticket_status_histories', function (Blueprint $table) {
            // old_status_id stays nullable — a ticket's very first history
            // row has no "old" status, same as the text column it replaces.
            $table->foreignId('new_status_id')->nullable(false)->change();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('type_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('category', ['Hardware', 'Software', 'Network', 'Account'])->nullable()->after('category_id');
            $table->enum('priority', ['Low', 'Medium', 'High'])->nullable()->after('priority_id');
            $table->enum('status', ['Open', 'Pending', 'In Progress', 'Rejected', 'Resolved'])->nullable()->after('status_id');
        });

        Schema::table('ticket_status_histories', function (Blueprint $table) {
            $table->enum('old_status', ['Open', 'Pending', 'In Progress', 'Rejected', 'Resolved'])->nullable()->after('old_status_id');
            $table->enum('new_status', ['Open', 'Pending', 'In Progress', 'Rejected', 'Resolved'])->nullable()->after('new_status_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->enum('type', ['meeting', 'deadline', 'reminder', 'other'])->nullable()->after('type_id');
        });

        DB::statement('UPDATE tickets t JOIN categories c ON c.id = t.category_id SET t.category = c.name');
        DB::statement('UPDATE tickets t JOIN priorities p ON p.id = t.priority_id SET t.priority = p.name');
        DB::statement('UPDATE tickets t JOIN statuses s ON s.id = t.status_id SET t.status = s.name');
        DB::statement('UPDATE ticket_status_histories h JOIN statuses s ON s.id = h.old_status_id SET h.old_status = s.name');
        DB::statement('UPDATE ticket_status_histories h JOIN statuses s ON s.id = h.new_status_id SET h.new_status = s.name');
        DB::statement('UPDATE events e JOIN event_types et ON et.id = e.type_id SET e.type = et.name');

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->change();
            $table->foreignId('priority_id')->nullable()->change();
            $table->foreignId('status_id')->nullable()->change();
        });

        Schema::table('ticket_status_histories', function (Blueprint $table) {
            $table->foreignId('new_status_id')->nullable()->change();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('type_id')->nullable()->change();
        });
    }
};
