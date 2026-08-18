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
        Schema::create('event_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // Always the event's creator today, but kept as its own FK
            // (like ticket_notifications.user_id) rather than reusing
            // events.created_by_user_id, in case reminders ever target
            // someone else later.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('preview');
            $table->timestamp('remind_at');
            $table->timestamp('sent_at')->nullable();
            $table->enum('status', ['pending', 'sent', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_reminders');
    }
};
