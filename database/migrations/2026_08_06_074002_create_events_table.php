<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->enum('type', ['meeting', 'deadline', 'reminder', 'other'])->default('other');
            $table->date('event_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();

            // Google Calendar sync bookkeeping — the event always saves here
            // first; syncing to Google is best-effort and never blocks it.
            $table->string('google_event_id')->nullable();
            $table->enum('google_sync_status', ['pending', 'synced', 'failed'])->default('pending');
            $table->text('google_sync_error')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
