<?php

namespace App\Services;

use App\DTO\StoreMessageDTO;
use App\DTO\StoreTicketDTO;
use App\DTO\UpdateTicketStatusDTO;
use App\Events\MessageSent;
use App\Events\TicketNotificationCreated;
use App\Events\TicketUpdated;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BotService
{
    // Free-tier Gemini models, tried in order — see https://ai.google.dev/gemini-api/docs/pricing
    // "lite" first: this bot only fills structured fields, doesn't need heavy reasoning,
    // and in testing it had far more free-tier capacity available than gemini-3.5-flash.
    private const MODELS = ['gemini-3.1-flash-lite', 'gemini-3.5-flash'];

    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    private const MAX_ITERATIONS = 6;

    private const CATEGORIES = ['Hardware', 'Software', 'Network', 'Account'];

    private const PRIORITIES = ['Low', 'Medium', 'High'];

    private const STATUSES = ['Open', 'Pending', 'In Progress', 'Approved', 'Rejected', 'Resolved'];

    public function __construct(
        private readonly TicketService $ticketService,
        private readonly TicketMessageService $messageService,
        private readonly TicketNotificationService $notificationService,
    ) {}

    /**
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @return array{reply: string, actions: array<int, array<string, mixed>>}
     */
    public function chat(array $messages, string $username, bool $isAdmin, bool $isHr): array
    {
        $apiKey = config('services.gemini.api_key');

        if (empty($apiKey)) {
            return [
                'reply' => "Integral Bot isn't configured yet — an administrator needs to add a Gemini API key to the backend.",
                'actions' => [],
            ];
        }

        $tools = $this->buildTools($isAdmin, $isHr);
        $contents = $this->toGeminiContents($messages);
        $actions = [];

        $systemPrompt = $this->buildSystemPrompt($username, $isAdmin, $isHr);

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $response = $this->callGemini($contents, $tools, $systemPrompt, $apiKey);

            if ($response === null) {
                return [
                    'reply' => "I couldn't reach Gemini right now — it may be busy or unreachable. Please try again in a moment.",
                    'actions' => $actions,
                ];
            }

            if ($response->failed()) {
                return [
                    'reply' => 'Integral Bot ran into a problem talking to Gemini. Please try again in a moment.',
                    'actions' => $actions,
                ];
            }

            $parts = $response->json('candidates.0.content.parts', []);

            if ($parts === []) {
                return [
                    'reply' => "I couldn't generate a response to that — could you rephrase?",
                    'actions' => $actions,
                ];
            }

            $functionCalls = array_values(array_filter($parts, fn ($p) => isset($p['functionCall'])));

            if ($functionCalls === []) {
                return ['reply' => $this->extractText($parts), 'actions' => $actions];
            }

            $contents[] = ['role' => 'model', 'parts' => $parts];

            $responseParts = [];

            foreach ($functionCalls as $part) {
                $name = (string) ($part['functionCall']['name'] ?? '');
                $args = (array) ($part['functionCall']['args'] ?? []);

                [$resultText, $action] = $this->executeTool($name, $args, $username, $isAdmin, $isHr);

                if ($action !== null) {
                    $actions[] = $action;
                }

                $responseParts[] = [
                    'functionResponse' => [
                        'name' => $name,
                        'response' => ['result' => $resultText],
                    ],
                ];
            }

            $contents[] = ['role' => 'user', 'parts' => $responseParts];
        }

        return [
            'reply' => "That's taking more steps than expected — could you try rephrasing your request?",
            'actions' => $actions,
        ];
    }

    /**
     * Tries each model in self::MODELS in order, moving to the next one only
     * when a model reports 503 (overloaded) or the connection itself fails.
     * Returns null only if every model in the list failed.
     *
     * @param  array<int, array<string, mixed>>  $contents
     * @param  array<int, array<string, mixed>>  $tools
     */
    private function callGemini(array $contents, array $tools, string $systemPrompt, string $apiKey): ?\Illuminate\Http\Client\Response
    {
        foreach (self::MODELS as $model) {
            try {
                $response = Http::connectTimeout(8)
                    ->timeout(20)
                    // HTTP/2 negotiation hangs for larger POST bodies on some Windows
                    // dev setups (curl/nghttp2 + AV/firewall inspection). Force 1.1.
                    ->withOptions(['curl' => [CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1]])
                    ->retry(2, 400, throw: false)
                    ->post(
                        self::API_BASE.$model.':generateContent?key='.$apiKey,
                        [
                            'contents' => $contents,
                            'tools' => [['functionDeclarations' => $tools]],
                            'systemInstruction' => ['parts' => ['text' => $systemPrompt]],
                            'generationConfig' => ['maxOutputTokens' => 1024],
                        ],
                    );
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                continue;
            }

            // Model overloaded — try the next one in the list instead of failing outright.
            if ($response->status() === 503) {
                continue;
            }

            return $response;
        }

        return null;
    }

    /**
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function toGeminiContents(array $messages): array
    {
        return array_map(fn (array $m) => [
            'role' => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => (string) $m['content']]],
        ], $messages);
    }

    /** @param array<int, array<string, mixed>> $parts */
    private function extractText(array $parts): string
    {
        $text = collect($parts)->pluck('text')->filter()->implode('');

        return $text !== '' ? $text : "I'm not sure how to respond to that.";
    }

    private function buildSystemPrompt(string $username, bool $isAdmin, bool $isHr): string
    {
        $role = $isAdmin ? 'Admin/Agent' : ($isHr ? 'HR' : 'Employee');

        return <<<PROMPT
        You are "Integral Bot", a helpful in-app assistant for the Integral support ticket system.
        The current user is "{$username}", role: {$role}.

        Rules:
        - Only use the tools you've been given. If you weren't given a tool for something, say you can't do that — never claim to have done something you didn't actually do.
        - If a tool needs information the user hasn't given you, ask one short, specific clarifying question instead of guessing or calling the tool with made-up values.
        - Ticket categories are exactly: Hardware, Software, Network, Account.
        - Ticket priorities are exactly: Low, Medium, High.
        - Ticket statuses are exactly: Open, Pending, In Progress, Approved, Rejected, Resolved.
        - Never invent ticket IDs, usernames, or data — only reference what tool results actually return to you.
        - Keep replies short and conversational (one to three sentences), not a report.
        - You only see and act on what the current user is allowed to see and act on in the app itself — don't apologize for that, just work within it.
        PROMPT;
    }

    /** @return array<int, array<string, mixed>> */
    private function buildTools(bool $isAdmin, bool $isHr): array
    {
        $navigateTargets = ['dashboard', 'tickets', 'calendar', '2fa-setup', 'settings', 'create-ticket'];

        if ($isAdmin || $isHr) {
            $navigateTargets[] = 'users';
            $navigateTargets[] = 'finance';
        }

        $tools = [
            [
                'name' => 'navigate',
                'description' => 'Open a page in the app for the user (client-side navigation). '
                    .'These names match the sidebar labels exactly: "dashboard" is the home/overview page with ticket stats, '
                    .'"tickets" is the ticket list page, "create-ticket" is the new-ticket form, "2fa-setup" is the two-factor '
                    .'authentication setup page. "dashboard" and "tickets" are NOT the same page — pick the one the user actually means.',
                'parameters' => [
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
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'mode' => ['type' => 'string', 'enum' => ['light', 'dark']],
                    ],
                    'required' => ['mode'],
                ],
            ],
            [
                'name' => 'create_ticket',
                'description' => 'Create a new support ticket on behalf of the current user. Call this only once you have all four fields.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Short ticket title, 3-255 characters.'],
                        'description' => ['type' => 'string', 'description' => 'Description of the issue, 3-200 characters.'],
                        'category' => ['type' => 'string', 'enum' => self::CATEGORIES],
                        'priority' => ['type' => 'string', 'enum' => self::PRIORITIES],
                    ],
                    'required' => ['title', 'description', 'category', 'priority'],
                ],
            ],
            [
                'name' => 'list_tickets',
                'description' => 'List support tickets visible to the current user, optionally filtered by status.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'enum' => self::STATUSES,
                            'description' => 'Optional. Omit to list tickets of every status.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_ticket_detail',
                'description' => 'Get full details for a single ticket by its numeric ID.',
                'parameters' => [
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
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'integer'],
                        'status' => ['type' => 'string', 'enum' => self::STATUSES],
                    ],
                    'required' => ['ticket_id', 'status'],
                ],
            ],
            [
                'name' => 'update_ticket_priority',
                'description' => 'Change the priority of a ticket.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'integer'],
                        'priority' => ['type' => 'string', 'enum' => self::PRIORITIES],
                    ],
                    'required' => ['ticket_id', 'priority'],
                ],
            ],
            [
                'name' => 'reply_to_ticket',
                'description' => 'Post a reply message on a ticket.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'integer'],
                        'message' => ['type' => 'string', 'description' => 'Message text, max 1000 characters.'],
                    ],
                    'required' => ['ticket_id', 'message'],
                ],
            ],
        ];

        if ($isAdmin || $isHr) {
            $tools[] = [
                'name' => 'create_user',
                'description' => 'Create a new user account. Only available to HR and Admin roles. A temporary password is generated automatically — never ask the user to type a password.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'username' => ['type' => 'string'],
                        'email' => ['type' => 'string'],
                        'is_hr' => ['type' => 'boolean', 'description' => 'Grant HR access. Defaults to false.'],
                        'is_admin' => ['type' => 'boolean', 'description' => 'Grant Admin/Agent access. Defaults to false.'],
                    ],
                    'required' => ['name', 'username', 'email'],
                ],
            ];

            $tools[] = [
                'name' => 'list_users',
                'description' => 'List all user accounts and their roles. Only available to HR and Admin roles.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass,
                ],
            ];
        }

        return $tools;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: string, 1: ?array<string, mixed>}
     */
    private function executeTool(string $name, array $input, string $username, bool $isAdmin, bool $isHr): array
    {
        return match ($name) {
            'navigate' => $this->toolNavigate($input, $isAdmin),
            'toggle_theme' => $this->toolToggleTheme($input),
            'create_ticket' => $this->toolCreateTicket($input, $username),
            'list_tickets' => $this->toolListTickets($input, $username, $isAdmin),
            'get_ticket_detail' => $this->toolGetTicketDetail($input, $username, $isAdmin),
            'update_ticket_status' => $this->toolUpdateTicketStatus($input, $username, $isAdmin),
            'update_ticket_priority' => $this->toolUpdateTicketPriority($input, $username, $isAdmin),
            'reply_to_ticket' => $this->toolReplyToTicket($input, $username, $isAdmin),
            'create_user' => ($isAdmin || $isHr)
                ? $this->toolCreateUser($input)
                : ['You do not have permission to create users.', null],
            'list_users' => ($isAdmin || $isHr)
                ? $this->toolListUsers()
                : ['You do not have permission to view users.', null],
            default => ["Unknown tool: {$name}", null],
        };
    }

    /** @param array<string, mixed> $input */
    private function toolNavigate(array $input, bool $isAdmin): array
    {
        // Keys match the sidebar's own labels exactly — NOT the raw route
        // path segments, which are named inconsistently in this app (the
        // sidebar's "Dashboard" link goes to /overview, and its "Tickets"
        // link goes to /dashboard or /agent-dashboard).
        $routes = [
            'dashboard' => '/overview',
            'tickets' => $isAdmin ? '/agent-dashboard' : '/dashboard',
            'calendar' => '/calendar',
            '2fa-setup' => '/setup-2fa',
            'settings' => '/settings',
            'create-ticket' => '/create-ticket',
            'users' => '/users',
            'finance' => '/finance',
        ];

        $page = (string) ($input['page'] ?? '');

        if (! isset($routes[$page])) {
            return ["That page doesn't exist or you don't have access to it.", null];
        }

        return ["Opening {$page}.", ['type' => 'navigate', 'path' => $routes[$page]]];
    }

    /** @param array<string, mixed> $input */
    private function toolToggleTheme(array $input): array
    {
        $mode = (string) ($input['mode'] ?? '');

        if (! in_array($mode, ['light', 'dark'], true)) {
            return ['Invalid theme mode.', null];
        }

        return ["Switched to {$mode} mode.", ['type' => 'theme', 'mode' => $mode]];
    }

    /** @param array<string, mixed> $input */
    private function toolCreateTicket(array $input, string $username): array
    {
        $validator = Validator::make($input, [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:3', 'max:200'],
            'category' => ['required', 'string', 'in:'.implode(',', self::CATEGORIES)],
            'priority' => ['required', 'string', 'in:'.implode(',', self::PRIORITIES)],
        ]);

        if ($validator->fails()) {
            return [$this->firstError($validator), null];
        }

        $dto = StoreTicketDTO::fromArray([
            ...$validator->validated(),
            'created_by' => $username,
        ]);

        $ticket = $this->ticketService->createTicket($dto);

        return ["Created ticket #{$ticket->id}: \"{$ticket->title}\" ({$ticket->category}, {$ticket->priority} priority, status Open).", null];
    }

    /** @param array<string, mixed> $input */
    private function toolListTickets(array $input, string $username, bool $isAdmin): array
    {
        $tickets = $this->ticketService->getAllTickets();

        if (! $isAdmin) {
            $tickets = $tickets->where('created_by', $username);
        }

        $status = $input['status'] ?? null;
        if (is_string($status) && $status !== '') {
            $tickets = $tickets->where('status', $status);
        }

        if ($tickets->isEmpty()) {
            return ['No tickets found.', null];
        }

        $lines = $tickets->take(20)->map(
            fn (Ticket $t) => "#{$t->id} \"{$t->title}\" — {$t->status}, {$t->priority} priority, {$t->category}, created by {$t->created_by}"
        )->implode("\n");

        return [$lines, null];
    }

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
            "Status: {$ticket->status}\n".
            "Priority: {$ticket->priority}\n".
            "Category: {$ticket->category}\n".
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

        $updated = $this->ticketService->updateTicketStatus($ticket, UpdateTicketStatusDTO::fromArray(['status' => $status]));
        broadcast(new TicketUpdated($updated));

        return ["Ticket #{$updated->id} status updated to {$updated->status}.", null];
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

        $updated = $this->ticketService->updateTicketPriority($ticket, $priority);
        broadcast(new TicketUpdated($updated));

        return ["Ticket #{$updated->id} priority updated to {$updated->priority}.", null];
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

        $dto = StoreMessageDTO::fromArray([
            'sender' => $username,
            'is_agent' => $isAdmin,
            'message' => $validator->validated()['message'],
        ]);

        $message = $this->messageService->createMessage($ticket->id, $dto);

        broadcast(new MessageSent($message))->toOthers();

        $notifications = $this->notificationService->notifyForNewMessage($message, $ticket);
        foreach ($notifications as $notification) {
            broadcast(new TicketNotificationCreated($notification, $notification->user->username));
        }

        return ["Reply posted on ticket #{$ticket->id}.", null];
    }

    /** @param array<string, mixed> $input */
    private function toolCreateUser(array $input): array
    {
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'is_hr' => ['boolean'],
            'is_admin' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return [$this->firstError($validator), null];
        }

        $data = $validator->validated();
        $password = Str::random(12);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($password),
            'is_admin' => $data['is_admin'] ?? false,
            'is_hr' => $data['is_hr'] ?? false,
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
