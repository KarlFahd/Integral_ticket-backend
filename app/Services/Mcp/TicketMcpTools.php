<?php

namespace App\Services\Mcp;

use App\DTO\StoreEventDTO;
use App\DTO\StoreMessageDTO;
use App\DTO\StoreTicketDTO;
use App\DTO\UpdateEventDTO;
use App\DTO\UpdateTicketStatusDTO;
use App\Events\MessageSent;
use App\Events\TicketNotificationCreated;
use App\Events\TicketUpdated;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EventService;
use App\Services\TicketMessageService;
use App\Services\TicketNotificationService;
use App\Services\TicketService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * TicketMcpTools — the MCP server's tool layer.
 *
 * This class has two public methods that directly map to MCP's two tool methods:
 *
 *   definitions()  ←→  MCP "tools/list"
 *     Returns the list of tools the AI can call, with their names, descriptions,
 *     and argument schemas (in JSON Schema format, using MCP's "inputSchema" field).
 *     The list is filtered by role — HR/Admin get extra tools.
 *
 *   call()         ←→  MCP "tools/call"
 *     Executes a named tool with the given arguments and returns the result in
 *     MCP's standard format: { content: [{type, text}] }.
 *     We extend this with a non-standard "action" field for frontend side-effects
 *     (navigation, theme) that a plain text result can't carry.
 *
 * All 12 tools are also available in the system prompt context through their
 * descriptions, which is how Gemini knows WHEN to call them and with what args.
 * The description text is as important as the implementation — a vague description
 * means Gemini picks the wrong tool or guesses arguments.
 *
 * Category/priority/status/event-type are numeric foreign keys in the database,
 * but the AI-facing tool schemas keep speaking text (e.g. "Hardware", not
 * "category_id: 1") — that's the natural chat experience, and it's what every
 * other copy of these four lists (the 6 wrapper classes in app/Mcp/Tools/,
 * PublicMcpTools, BotService's system prompt) reads from these public
 * constants, so there's exactly one place that knows the valid text values.
 * Each tool method below validates the text exactly as before, then does one
 * extra step — resolve the matching row's id — right before saving.
 */
class TicketMcpTools
{
    public const CATEGORIES = ['Hardware', 'Software', 'Network', 'Account'];

    public const PRIORITIES = ['Low', 'Medium', 'High'];

    public const STATUSES = ['Open', 'Pending', 'In Progress', 'Rejected', 'Resolved'];

    public const EVENT_TYPES = ['meeting', 'deadline', 'reminder', 'other'];

    public function __construct(
        private readonly TicketService $ticketService,
        private readonly TicketMessageService $messageService,
        private readonly TicketNotificationService $notificationService,
        private readonly EventService $eventService,
    ) {}

    /**
     * Returns the MCP tool list — the full catalog of what the AI can do.
     *
     * Each tool is described using JSON Schema in the "inputSchema" field
     * (MCP's name for it). BotService::mcpToolsToGeminiDeclarations() renames
     * that to "parameters" before sending it to Gemini — the schema itself is
     * identical either way.
     *
     * Why role-filter here instead of in McpController?
     * Because this is domain logic ("HR can create users"), not transport logic.
     * McpController only knows about the wire format.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitions(bool $isAdmin, bool $isHr): array
    {
        // The navigate tool's page enum is role-scoped: non-admin users shouldn't
        // be able to ask the bot to "go to history" or "go to users".
        $navigateTargets = ['dashboard', 'tickets', 'calendar', '2fa-setup', 'settings', 'create-ticket'];

        if ($isAdmin || $isHr) {
            $navigateTargets[] = 'users';
            $navigateTargets[] = 'finance';
        }

        if ($isAdmin) {
            $navigateTargets[] = 'history';
        }

        $tools = [
            [
                'name' => 'navigate',
                // The description tells Gemini exactly when to pick this tool
                // and how to distinguish similar-sounding targets ("dashboard"
                // vs "tickets" are different pages — the description says so explicitly).
                'description' => 'Open a page in the app for the user (client-side navigation). '
                    .'These names match the sidebar labels exactly: "dashboard" is the home/overview page with ticket stats, '
                    .'"tickets" is the ticket list page, "create-ticket" is the new-ticket form, "2fa-setup" is the two-factor '
                    .'authentication setup page, "history" is the admin-only archive of resolved tickets (separate from "tickets", '
                    .'which never shows resolved tickets). "dashboard" and "tickets" are NOT the same page — pick the one the user actually means.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'page' => ['type' => 'string', 'enum' => $navigateTargets],
                    ],
                    'required' => ['page'],
                ],
            ],
            [
                'name' => 'toggle_theme',
                'description' => 'Switch the app between light and dark mode.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'mode' => ['type' => 'string', 'enum' => ['light', 'dark']],
                    ],
                    'required' => ['mode'],
                ],
            ],
            [
                'name' => 'list_tickets',
                'description' => 'List support tickets visible to the current user, optionally filtered by status.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type'        => 'string',
                            'enum'        => self::STATUSES,
                            'description' => 'Optional. Omit to list tickets of every status.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_ticket_detail',
                'description' => 'Get full details for a single ticket by its numeric ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'integer'],
                    ],
                    'required' => ['ticket_id'],
                ],
            ],
            [
                'name' => 'update_ticket_status',
                'description' => 'Change the status of a ticket (e.g. close it, mark it resolved).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'integer'],
                        'status'    => ['type' => 'string', 'enum' => self::STATUSES],
                    ],
                    'required' => ['ticket_id', 'status'],
                ],
            ],
            [
                'name' => 'update_ticket_priority',
                'description' => 'Change the priority of a ticket.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'integer'],
                        'priority'  => ['type' => 'string', 'enum' => self::PRIORITIES],
                    ],
                    'required' => ['ticket_id', 'priority'],
                ],
            ],
            [
                'name' => 'reply_to_ticket',
                'description' => 'Post a reply message on a single ticket.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'integer'],
                        'message'   => ['type' => 'string', 'description' => 'Message text, max 1000 characters.'],
                    ],
                    'required' => ['ticket_id', 'message'],
                ],
            ],
            [
                'name' => 'bulk_reply_to_tickets',
                'description' => 'Post the SAME reply message to every ticket matching the given filters — use this '
                    .'instead of calling reply_to_ticket once per ticket whenever the user asks to message "all tickets" '
                    .'matching some condition (e.g. "message every High priority ticket", "send this to all tickets with '
                    .'ID under 10"). At least one filter (status, priority, min_id, or max_id) is required — refuse and '
                    .'ask for a filter if the user wants it sent to literally every ticket with no condition at all. '
                    .'Resolved tickets are always excluded, even if the filters would otherwise match them — they\'re '
                    .'archived in the History page and considered done, so they never receive bulk messages.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'message'  => ['type' => 'string', 'description' => 'Message text, max 1000 characters.'],
                        'status'   => ['type' => 'string', 'enum' => self::STATUSES, 'description' => 'Optional filter: only tickets with this status.'],
                        'priority' => ['type' => 'string', 'enum' => self::PRIORITIES, 'description' => 'Optional filter: only tickets with this priority.'],
                        'min_id'   => ['type' => 'integer', 'description' => 'Optional filter: only tickets with an ID greater than this.'],
                        'max_id'   => ['type' => 'integer', 'description' => 'Optional filter: only tickets with an ID less than this.'],
                    ],
                    'required' => ['message'],
                ],
            ],
            [
                'name' => 'create_event',
                'description' => 'Create a calendar event on the shared company Google Calendar on behalf of the current user '
                    .'(e.g. a meeting, deadline, or reminder). Call this only once you have title, description, type, date, and '
                    .'start time. event_date must be an actual calendar date in YYYY-MM-DD format — if the user says something '
                    .'relative like "tomorrow" or "next Monday", work out the real date yourself using today\'s date from your '
                    .'system instructions before calling this tool; never pass the word "tomorrow" itself as event_date. '
                    .'participant_usernames is optional — only include it if the user names specific people to invite by their '
                    .'exact username; unrecognized usernames are silently skipped, so don\'t guess at spelling.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'title'       => ['type' => 'string', 'description' => 'Short event title, 3-255 characters.'],
                        'description' => ['type' => 'string', 'description' => 'What the event is about, 3-2000 characters.'],
                        'type'        => ['type' => 'string', 'enum' => self::EVENT_TYPES],
                        'event_date'  => ['type' => 'string', 'description' => 'Actual calendar date, format YYYY-MM-DD.'],
                        'start_time'  => ['type' => 'string', 'description' => '24-hour time, format HH:MM.'],
                        'end_time'    => ['type' => 'string', 'description' => 'Optional. 24-hour time, format HH:MM. Must be after start_time.'],
                        'participant_usernames' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Optional. Exact usernames of other users to invite.',
                        ],
                    ],
                    'required' => ['title', 'description', 'type', 'event_date', 'start_time'],
                ],
            ],
            [
                'name' => 'list_events',
                'description' => 'List all events on the shared company calendar (meetings, deadlines, reminders). '
                    .'Use this first when the user refers to an event by a fuzzy title, so you can find its numeric '
                    .'ID before calling get_event_detail, update_event, or delete_event.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new \stdClass,
                ],
            ],
            [
                'name' => 'get_event_detail',
                'description' => 'Get full details for a single calendar event by its numeric ID, including its '
                    .'current participants — call this before update_event so you know the exact current values '
                    .'(update_event requires every field to be resent, not just the one changing).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'event_id' => ['type' => 'integer'],
                    ],
                    'required' => ['event_id'],
                ],
            ],
            [
                'name' => 'update_event',
                'description' => 'Edit an existing calendar event. This replaces the event\'s full details — you '
                    .'must resend every field (title, description, type, event_date, start_time), not just the one '
                    .'that\'s changing, so call get_event_detail first to see the current values. '
                    .'participant_usernames REPLACES the entire participant list; if you omit it, all participants '
                    .'are removed — to keep existing participants, pass their exact usernames again (from '
                    .'get_event_detail).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'event_id'    => ['type' => 'integer'],
                        'title'       => ['type' => 'string', 'description' => 'Short event title, 3-255 characters.'],
                        'description' => ['type' => 'string', 'description' => 'What the event is about, 3-2000 characters.'],
                        'type'        => ['type' => 'string', 'enum' => self::EVENT_TYPES],
                        'event_date'  => ['type' => 'string', 'description' => 'Actual calendar date, format YYYY-MM-DD.'],
                        'start_time'  => ['type' => 'string', 'description' => '24-hour time, format HH:MM.'],
                        'end_time'    => ['type' => 'string', 'description' => 'Optional. 24-hour time, format HH:MM. Must be after start_time.'],
                        'participant_usernames' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Replaces the full participant list. Omit to clear all participants — '
                                .'pass the existing usernames back to keep them.',
                        ],
                    ],
                    'required' => ['event_id', 'title', 'description', 'type', 'event_date', 'start_time'],
                ],
            ],
            [
                'name' => 'delete_event',
                'description' => 'Permanently cancel a calendar event by its numeric ID — also removes it from the '
                    .'shared Google Calendar. If you only have a title, call list_events first to find the ID. '
                    .'Ask the user to confirm before calling this, since it can\'t be undone.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'event_id' => ['type' => 'integer'],
                    ],
                    'required' => ['event_id'],
                ],
            ],
        ];

        // Tickets are how Employees and HR report a problem to an Admin/Agent
        // — an Admin creating a ticket would mean talking to themselves, so
        // this tool isn't even offered to them (they reply to/manage
        // tickets instead, via the other ticket tools below).
        if (! $isAdmin) {
            $tools[] = [
                'name' => 'create_ticket',
                'description' => 'Create a new support ticket on behalf of the current user, to be picked up by an '
                    .'Admin/Agent. Call this only once you have all four fields.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'title'       => ['type' => 'string', 'description' => 'Short ticket title, 3-255 characters.'],
                        'description' => ['type' => 'string', 'description' => 'Description of the issue, 3-200 characters.'],
                        'category'    => ['type' => 'string', 'enum' => self::CATEGORIES],
                        'priority'    => ['type' => 'string', 'enum' => self::PRIORITIES],
                    ],
                    'required' => ['title', 'description', 'category', 'priority'],
                ],
            ];
        }

        // HR and Admin-only tools — not included in the definitions list for
        // regular employees, so Gemini won't even try to call them.
        if ($isAdmin || $isHr) {
            $tools[] = [
                'name' => 'create_user',
                'description' => 'Create a new user account. Only available to HR and Admin roles. A temporary password is generated automatically — never ask the user to type a password.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name'     => ['type' => 'string'],
                        'username' => ['type' => 'string'],
                        'email'    => ['type' => 'string'],
                        'is_hr'    => ['type' => 'boolean', 'description' => 'Grant HR access. Defaults to false.'],
                        'is_admin' => ['type' => 'boolean', 'description' => 'Grant Admin/Agent access. Defaults to false.'],
                    ],
                    'required' => ['name', 'username', 'email'],
                ],
            ];

            $tools[] = [
                'name' => 'list_users',
                'description' => 'List all user accounts and their roles. Only available to HR and Admin roles.',
                'inputSchema' => [
                    'type' => 'object',
                    // No arguments needed — but MCP still requires "properties" to be
                    // a JSON object ({}), not a JSON array ([]). We use stdClass here
                    // because PHP encodes an empty array as [] but stdClass as {}.
                    'properties' => new \stdClass,
                ],
            ];
        }

        return $tools;
    }

    /**
     * MCP "tools/call" — dispatches to the right tool method and wraps the
     * result in MCP's standard response shape.
     *
     * Return shape:
     *   {
     *     content: [{ type: "text", text: "..." }],   ← standard MCP
     *     action:  { type: "navigate", path: "..." }  ← non-standard extension (can be null)
     *   }
     *
     * The "action" field is our own invention — MCP has no way for a tool to
     * tell the frontend "navigate to this page" or "switch to dark mode".
     * BotService collects actions across all tool calls in the agentic loop
     * and returns them alongside the final text reply to IntegralBot.vue,
     * which calls applyActions() to actually perform them.
     *
     * @param  array<string, mixed>  $input
     * @return array{content: array<int, array{type: string, text: string}>, action: ?array<string, mixed>}
     */
    public function call(string $name, array $input, string $username, bool $isAdmin, bool $isHr): array
    {
        // Each private method returns [string $resultText, ?array $action].
        [$resultText, $action] = match ($name) {
            'navigate'              => $this->toolNavigate($input, $isAdmin),
            'toggle_theme'          => $this->toolToggleTheme($input),
            'create_ticket'         => $this->toolCreateTicket($input, $username, $isAdmin),
            'list_tickets'          => $this->toolListTickets($input, $username, $isAdmin),
            'get_ticket_detail'     => $this->toolGetTicketDetail($input, $username, $isAdmin),
            'update_ticket_status'  => $this->toolUpdateTicketStatus($input, $username, $isAdmin),
            'update_ticket_priority'=> $this->toolUpdateTicketPriority($input, $username, $isAdmin),
            'reply_to_ticket'       => $this->toolReplyToTicket($input, $username, $isAdmin),
            'bulk_reply_to_tickets' => $this->toolBulkReplyToTickets($input, $username, $isAdmin),
            'create_event'          => $this->toolCreateEvent($input, $username),
            'list_events'           => $this->toolListEvents(),
            'get_event_detail'      => $this->toolGetEventDetail($input),
            'update_event'          => $this->toolUpdateEvent($input),
            'delete_event'          => $this->toolDeleteEvent($input),
            // Double-check role even though definitions() already filtered — defense in depth.
            'create_user' => ($isAdmin || $isHr)
                ? $this->toolCreateUser($input)
                : ['You do not have permission to create users.', null],
            'list_users' => ($isAdmin || $isHr)
                ? $this->toolListUsers()
                : ['You do not have permission to view users.', null],
            default => ["Unknown tool: {$name}", null],
        };

        // Wrap in MCP's result shape.
        return [
            'content' => [['type' => 'text', 'text' => $resultText]],
            'action'  => $action,
        ];
    }

    /** @param array<string, mixed> $input */
    private function toolNavigate(array $input, bool $isAdmin): array
    {
        // Keys match the sidebar's own labels exactly — NOT the raw route
        // path segments, which are named inconsistently in this app (the
        // sidebar's "Dashboard" link goes to /overview, and its "Tickets"
        // link goes to /dashboard or /agent-dashboard).
        $routes = [
            'dashboard'     => '/overview',
            'tickets'       => $isAdmin ? '/agent-dashboard' : '/dashboard',
            'calendar'      => '/calendar',
            '2fa-setup'     => '/setup-2fa',
            'settings'      => '/settings',
            'create-ticket' => '/create-ticket',
            'users'         => '/users',
            'finance'       => '/finance',
            'history'       => '/history',
        ];

        $page = (string) ($input['page'] ?? '');

        if (! isset($routes[$page])) {
            return ["That page doesn't exist or you don't have access to it.", null];
        }

        // The "action" payload is picked up by BotService and eventually
        // passed to IntegralBot.vue → applyActions() → router.push(path).
        return ["Opening {$page}.", ['type' => 'navigate', 'path' => $routes[$page]]];
    }

    /** @param array<string, mixed> $input */
    private function toolToggleTheme(array $input): array
    {
        $mode = (string) ($input['mode'] ?? '');

        if (! in_array($mode, ['light', 'dark'], true)) {
            return ['Invalid theme mode.', null];
        }

        // IntegralBot.vue → applyActions() → themeStore.setDark(mode === 'dark')
        return ["Switched to {$mode} mode.", ['type' => 'theme', 'mode' => $mode]];
    }

    /** @param array<string, mixed> $input */
    private function toolCreateTicket(array $input, string $username, bool $isAdmin): array
    {
        // Defense in depth — definitions() already hides this tool from
        // Admin/Agent, but a model could still be prompted to try it.
        if ($isAdmin) {
            return ["Tickets are how Employees and HR report a problem to an Agent — as an Admin/Agent, you'd be creating a ticket to talk to yourself, which isn't the point. Did you mean to reply to or update an existing ticket instead?", null];
        }

        $validator = Validator::make($input, [
            'title'       => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:3', 'max:200'],
            'category'    => ['required', 'string', 'in:'.implode(',', self::CATEGORIES)],
            'priority'    => ['required', 'string', 'in:'.implode(',', self::PRIORITIES)],
        ]);

        if ($validator->fails()) {
            // Return the first validation error as a text string so Gemini
            // can relay it to the user and ask for the correct value.
            return [$this->firstError($validator), null];
        }

        $data = $validator->validated();

        $dto = StoreTicketDTO::fromArray([
            'title' => $data['title'],
            'description' => $data['description'],
            'category_id' => Category::where('name', $data['category'])->value('id'),
            'priority_id' => Priority::where('name', $data['priority'])->value('id'),
            'created_by' => $username,
        ]);

        $ticket = $this->ticketService->createTicket($dto);

        return ["Created ticket #{$ticket->id}: \"{$ticket->title}\" ({$ticket->category->name}, {$ticket->priority->name} priority, status Open).", null];
    }

    /** @param array<string, mixed> $input */
    private function toolListTickets(array $input, string $username, bool $isAdmin): array
    {
        $tickets = $this->ticketService->getAllTickets();

        // Employees and HR only see their own tickets; admin sees all.
        if (! $isAdmin) {
            $tickets = $tickets->where('created_by', $username);
        }

        $status = $input['status'] ?? null;
        if (is_string($status) && $status !== '') {
            $tickets = $tickets->where('status.name', $status);
        }

        if ($tickets->isEmpty()) {
            return ['No tickets found.', null];
        }

        // Cap at 20 to keep the text result short enough for Gemini to summarise.
        $lines = $tickets->take(20)->map(
            fn (Ticket $t) => "#{$t->id} \"{$t->title}\" — {$t->status->name}, {$t->priority->name} priority, {$t->category->name}, created by {$t->created_by}"
        )->implode("\n");

        return [$lines, null];
    }

    /**
     * Shared helper: fetch a ticket by ID and verify the caller has access.
     * Returns null if the ticket doesn't exist OR the caller is a non-admin
     * trying to access someone else's ticket. The caller returns a safe error
     * message in that case — we never reveal whether the ticket exists.
     */
    private function findScopedTicket(int $ticketId, string $username, bool $isAdmin): ?Ticket
    {
        $ticket = $this->ticketService->getTicketById($ticketId);

        if ($ticket === null) {
            return null;
        }

        if (! $isAdmin && $ticket->created_by !== $username) {
            return null;
        }

        return $ticket;
    }

    /** @param array<string, mixed> $input */
    private function toolGetTicketDetail(array $input, string $username, bool $isAdmin): array
    {
        $ticket = $this->findScopedTicket((int) ($input['ticket_id'] ?? 0), $username, $isAdmin);

        if ($ticket === null) {
            return ["Ticket not found (or you don't have access to it).", null];
        }

        return [
            "Ticket #{$ticket->id}: \"{$ticket->title}\"\n".
            "Status: {$ticket->status->name}\n".
            "Priority: {$ticket->priority->name}\n".
            "Category: {$ticket->category->name}\n".
            "Created by: {$ticket->created_by}\n".
            "Description: {$ticket->description}",
            null,
        ];
    }

    /** @param array<string, mixed> $input */
    private function toolUpdateTicketStatus(array $input, string $username, bool $isAdmin): array
    {
        $ticket = $this->findScopedTicket((int) ($input['ticket_id'] ?? 0), $username, $isAdmin);

        if ($ticket === null) {
            return ["Ticket not found (or you don't have access to it).", null];
        }

        $status = (string) ($input['status'] ?? '');
        if (! in_array($status, self::STATUSES, true)) {
            return ['Invalid status value.', null];
        }

        $statusId = Status::where('name', $status)->value('id');
        $updated = $this->ticketService->updateTicketStatus($ticket, UpdateTicketStatusDTO::fromArray(['status_id' => $statusId]));
        // Broadcast the update so the web and mobile clients refresh in real-time.
        broadcast(new TicketUpdated($updated));

        return ["Ticket #{$updated->id} status updated to {$updated->status->name}.", null];
    }

    /** @param array<string, mixed> $input */
    private function toolUpdateTicketPriority(array $input, string $username, bool $isAdmin): array
    {
        $ticket = $this->findScopedTicket((int) ($input['ticket_id'] ?? 0), $username, $isAdmin);

        if ($ticket === null) {
            return ["Ticket not found (or you don't have access to it).", null];
        }

        $priority = (string) ($input['priority'] ?? '');
        if (! in_array($priority, self::PRIORITIES, true)) {
            return ['Invalid priority value.', null];
        }

        $priorityId = Priority::where('name', $priority)->value('id');
        $updated = $this->ticketService->updateTicketPriority($ticket, $priorityId);
        broadcast(new TicketUpdated($updated));

        return ["Ticket #{$updated->id} priority updated to {$updated->priority->name}.", null];
    }

    /** @param array<string, mixed> $input */
    private function toolReplyToTicket(array $input, string $username, bool $isAdmin): array
    {
        $ticket = $this->findScopedTicket((int) ($input['ticket_id'] ?? 0), $username, $isAdmin);

        if ($ticket === null) {
            return ["Ticket not found (or you don't have access to it).", null];
        }

        $validator = Validator::make($input, [
            'message' => ['required', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return [$this->firstError($validator), null];
        }

        $this->postTicketMessage($ticket, $username, $isAdmin, $validator->validated()['message']);

        return ["Reply posted on ticket #{$ticket->id}.", null];
    }

    /** @param array<string, mixed> $input */
    private function toolBulkReplyToTickets(array $input, string $username, bool $isAdmin): array
    {
        $validator = Validator::make($input, [
            'message'  => ['required', 'string', 'max:1000'],
            'status'   => ['nullable', 'string', 'in:'.implode(',', self::STATUSES)],
            'priority' => ['nullable', 'string', 'in:'.implode(',', self::PRIORITIES)],
            'min_id'   => ['nullable', 'integer'],
            'max_id'   => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return [$this->firstError($validator), null];
        }

        $data = $validator->validated();

        // Require at least one filter — the description also tells Gemini this,
        // but we enforce it here too so a misbehaving model can't blast every ticket.
        if (! isset($data['status']) && ! isset($data['priority']) && ! isset($data['min_id']) && ! isset($data['max_id'])) {
            return ['Please give me at least one filter (status, priority, or a ticket ID range) so I know which tickets to message — I won\'t broadcast to every single ticket.', null];
        }

        $tickets = $this->ticketService->getAllTickets();

        // Resolved tickets are archived in the History page — they're done,
        // so bulk messages never target them even if a filter would otherwise match.
        $tickets = $tickets->where('status.name', '!=', 'Resolved');

        if (! $isAdmin) {
            $tickets = $tickets->where('created_by', $username);
        }

        if (isset($data['status'])) {
            $tickets = $tickets->where('status.name', $data['status']);
        }

        if (isset($data['priority'])) {
            $tickets = $tickets->where('priority.name', $data['priority']);
        }

        if (isset($data['min_id'])) {
            $tickets = $tickets->where('id', '>', $data['min_id']);
        }

        if (isset($data['max_id'])) {
            $tickets = $tickets->where('id', '<', $data['max_id']);
        }

        if ($tickets->isEmpty()) {
            return ['No tickets matched those filters — nothing was sent.', null];
        }

        // Hard cap at 50 to prevent accidental mass-messaging.
        $tickets = $tickets->take(50);

        foreach ($tickets as $ticket) {
            $this->postTicketMessage($ticket, $username, $isAdmin, $data['message']);
        }

        $count = $tickets->count();

        return ["Sent the message to {$count} ticket".($count === 1 ? '' : 's').': '.$tickets->map(fn (Ticket $t) => "#{$t->id}")->implode(', ').'.', null];
    }

    /** @param array<string, mixed> $input */
    private function toolCreateEvent(array $input, string $username): array
    {
        $validator = Validator::make($input, [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:3', 'max:2000'],
            'type' => ['required', 'string', 'in:'.implode(',', self::EVENT_TYPES)],
            'event_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'participant_usernames' => ['nullable', 'array'],
            'participant_usernames.*' => ['string'],
        ]);

        if ($validator->fails()) {
            return [$this->firstError($validator), null];
        }

        $data = $validator->validated();
        $data['type_id'] = EventType::where('name', $data['type'])->value('id');

        $creator = User::where('username', $username)->first();
        if ($creator === null) {
            return ["Couldn't identify your account — please try again.", null];
        }

        // Unrecognized usernames are silently dropped rather than erroring —
        // the model can't be trusted to spell an exact username correctly,
        // so a near-miss shouldn't block the whole event from being created.
        $participantIds = User::whereIn('username', $data['participant_usernames'] ?? [])->pluck('id')->all();

        $event = $this->eventService->createEvent(
            StoreEventDTO::fromArray($data, $creator->id, $participantIds)
        );

        $syncNote = $event->google_sync_status === 'synced'
            ? " It's on the shared Google Calendar."
            : ' It\'s saved, but syncing to Google Calendar is still pending.';

        $time = substr((string) $event->start_time, 0, 5);

        return [
            "Created \"{$event->title}\" ({$event->type->name}) on {$event->event_date->format('M j, Y')} at {$time}.{$syncNote}",
            null,
        ];
    }

    private function toolListEvents(): array
    {
        $events = $this->eventService->getAllEvents();

        if ($events->isEmpty()) {
            return ['No events found.', null];
        }

        $lines = $events->take(20)->map(function (Event $e) {
            $time = substr((string) $e->start_time, 0, 5);

            return "#{$e->id} \"{$e->title}\" — {$e->type->name}, {$e->event_date->format('M j, Y')} at {$time}, created by {$e->creator->name}";
        })->implode("\n");

        return [$lines, null];
    }

    /** @param array<string, mixed> $input */
    private function toolGetEventDetail(array $input): array
    {
        $event = $this->eventService->getEventById((int) ($input['event_id'] ?? 0));

        if ($event === null) {
            return ['Event not found.', null];
        }

        $time = substr((string) $event->start_time, 0, 5);
        $endTime = $event->end_time !== null ? substr((string) $event->end_time, 0, 5) : null;
        $participants = $event->participants->pluck('username')->implode(', ');

        return [
            "Event #{$event->id}: \"{$event->title}\"\n".
            "Type: {$event->type->name}\n".
            "Date: {$event->event_date->format('M j, Y')}\n".
            'Time: '.$time.($endTime !== null ? " - {$endTime}" : '')."\n".
            "Created by: {$event->creator->name} (@{$event->creator->username})\n".
            'Participants: '.($participants !== '' ? $participants : 'none')."\n".
            "Description: {$event->description}\n".
            "Google Calendar sync: {$event->google_sync_status}",
            null,
        ];
    }

    /** @param array<string, mixed> $input */
    private function toolUpdateEvent(array $input): array
    {
        $event = $this->eventService->getEventById((int) ($input['event_id'] ?? 0));

        if ($event === null) {
            return ['Event not found.', null];
        }

        $validator = Validator::make($input, [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:3', 'max:2000'],
            'type' => ['required', 'string', 'in:'.implode(',', self::EVENT_TYPES)],
            'event_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'participant_usernames' => ['nullable', 'array'],
            'participant_usernames.*' => ['string'],
        ]);

        if ($validator->fails()) {
            return [$this->firstError($validator), null];
        }

        $data = $validator->validated();
        $data['type_id'] = EventType::where('name', $data['type'])->value('id');
        $participantIds = User::whereIn('username', $data['participant_usernames'] ?? [])->pluck('id')->all();

        $updated = $this->eventService->updateEvent($event, UpdateEventDTO::fromArray($data, $participantIds));

        $time = substr((string) $updated->start_time, 0, 5);

        return ["Updated \"{$updated->title}\" ({$updated->type->name}) on {$updated->event_date->format('M j, Y')} at {$time}.", null];
    }

    /** @param array<string, mixed> $input */
    private function toolDeleteEvent(array $input): array
    {
        $event = $this->eventService->getEventById((int) ($input['event_id'] ?? 0));

        if ($event === null) {
            return ['Event not found.', null];
        }

        $title = $event->title;
        $this->eventService->deleteEvent($event);

        return ["Deleted event \"{$title}\".", null];
    }

    /**
     * Shared helper used by both reply_to_ticket and bulk_reply_to_tickets.
     * Creates the message in the database, broadcasts it via WebSocket so
     * open ticket detail pages update in real-time, and sends notifications.
     */
    private function postTicketMessage(Ticket $ticket, string $username, bool $isAdmin, string $text): void
    {
        $dto = StoreMessageDTO::fromArray([
            'sender'   => $username,
            'is_agent' => $isAdmin,
            'message'  => $text,
        ]);

        $message = $this->messageService->createMessage($ticket->id, $dto);

        // .toOthers() excludes the sender's own WebSocket connection — prevents
        // duplicate messages on the sender's screen (they already see it via the
        // HTTP response in the message history they just reloaded).
        broadcast(new MessageSent($message))->toOthers();

        $notifications = $this->notificationService->notifyForNewMessage($message, $ticket);
        foreach ($notifications as $notification) {
            broadcast(new TicketNotificationCreated($notification, $notification->user->username));
        }
    }

    /** @param array<string, mixed> $input */
    private function toolCreateUser(array $input): array
    {
        $validator = Validator::make($input, [
            'name'     => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'is_hr'    => ['boolean'],
            'is_admin' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return [$this->firstError($validator), null];
        }

        $data     = $validator->validated();
        $password = Str::random(12);

        $user = User::create([
            'name'     => $data['name'],
            'username' => $data['username'],
            'email'    => $data['email'],
            'password' => Hash::make($password),
            'is_admin' => $data['is_admin'] ?? false,
            'is_hr'    => $data['is_hr'] ?? false,
        ]);

        return [
            "Created user \"{$user->username}\" ({$user->name}). Temporary password: {$password} — share this with them securely, they should change it after first login.",
            null,
        ];
    }

    private function toolListUsers(): array
    {
        $users = User::orderBy('name')->get();

        if ($users->isEmpty()) {
            return ['No users found.', null];
        }

        $lines = $users->map(function (User $u) {
            $role = $u->is_admin ? 'Admin' : ($u->is_hr ? 'HR' : 'Employee');

            return "{$u->name} (@{$u->username}) — {$role}";
        })->implode("\n");

        return [$lines, null];
    }

    private function firstError(\Illuminate\Contracts\Validation\Validator $validator): string
    {
        return (string) collect($validator->errors()->all())->first();
    }
}
