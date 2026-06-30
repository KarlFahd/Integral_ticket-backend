<?php

use App\Models\Ticket;

// ─── POST /api/tickets ────────────────────────────────────────────────────────

it('creates a ticket successfully', function () {
    $response = $this->postJson('/api/tickets', [
        'title' => 'Outlook not working',
        'description' => 'Cannot open Outlook since this morning.',
        'category' => 'software',
        'priority' => 'high',
        'created_by' => 'john.doe',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id', 'title', 'description', 'category',
                'priority', 'status', 'created_by', 'status_history',
            ],
        ])
        ->assertJsonPath('data.status', 'Open')
        ->assertJsonPath('data.title', 'Outlook not working');

    $this->assertDatabaseHas('tickets', ['title' => 'Outlook not working']);
    $this->assertDatabaseHas('ticket_status_histories', ['new_status' => 'Open']);
});

it('fails to create a ticket when title is missing', function () {
    $response = $this->postJson('/api/tickets', [
        'description' => 'No title provided.',
        'category' => 'software',
        'priority' => 'low',
        'created_by' => 'john.doe',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

it('fails to create a ticket with an invalid priority', function () {
    $response = $this->postJson('/api/tickets', [
        'title' => 'VPN issue',
        'description' => 'Cannot connect to VPN from home.',
        'category' => 'network',
        'priority' => 'critical',
        'created_by' => 'jane.doe',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['priority']);
});

it('fails to create a ticket with an invalid category', function () {
    $response = $this->postJson('/api/tickets', [
        'title' => 'Random issue',
        'description' => 'Some random issue happening.',
        'category' => 'unknown',
        'priority' => 'low',
        'created_by' => 'john.doe',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['category']);
});

// ─── GET /api/tickets ─────────────────────────────────────────────────────────

it('returns a list of tickets', function () {
    Ticket::factory()->count(3)->create();

    $response = $this->getJson('/api/tickets');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

it('returns an empty list when there are no tickets', function () {
    $response = $this->getJson('/api/tickets');

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

// ─── GET /api/tickets/{id} ────────────────────────────────────────────────────

it('returns a single ticket by id', function () {
    $ticket = Ticket::factory()->create(['title' => 'Password Reset']);

    $response = $this->getJson("/api/tickets/{$ticket->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.title', 'Password Reset');
});

it('returns 404 when ticket does not exist', function () {
    $response = $this->getJson('/api/tickets/9999');

    $response->assertStatus(404)
        ->assertJsonPath('message', 'Ticket not found.');
});

// ─── PATCH /api/tickets/{id}/status ──────────────────────────────────────────

it('updates ticket status and records history', function () {
    $ticket = Ticket::factory()->create(['status' => 'Open']);

    $response = $this->patchJson("/api/tickets/{$ticket->id}/status", [
        'status' => 'In Progress',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'In Progress');

    $this->assertDatabaseHas('ticket_status_histories', [
        'ticket_id' => $ticket->id,
        'old_status' => 'Open',
        'new_status' => 'In Progress',
    ]);
});

it('fails to update status with an invalid value', function () {
    $ticket = Ticket::factory()->create();

    $response = $this->patchJson("/api/tickets/{$ticket->id}/status", [
        'status' => 'Deleted',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('returns 404 when updating status of a non-existent ticket', function () {
    $response = $this->patchJson('/api/tickets/9999/status', [
        'status' => 'Resolved',
    ]);

    $response->assertStatus(404);
});

// ─── PATCH /api/tickets/{id}/priority ────────────────────────────────────────

it('updates ticket priority successfully', function () {
    $ticket = Ticket::factory()->create(['priority' => 'low']);

    $response = $this->patchJson("/api/tickets/{$ticket->id}/priority", [
        'priority' => 'high',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.priority', 'high');

    $this->assertDatabaseHas('tickets', [
        'id' => $ticket->id,
        'priority' => 'high',
    ]);
});

it('fails to update priority with an invalid value', function () {
    $ticket = Ticket::factory()->create();

    $response = $this->patchJson("/api/tickets/{$ticket->id}/priority", [
        'priority' => 'critical',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['priority']);
});

it('returns 404 when updating priority of a non-existent ticket', function () {
    $response = $this->patchJson('/api/tickets/9999/priority', [
        'priority' => 'high',
    ]);

    $response->assertStatus(404);
});
