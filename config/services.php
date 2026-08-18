<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

    'mcp' => [
        // Shared secret McpController checks on every request, and
        // McpHttpClient sends on every request — see AppServiceProvider
        // for where the URL + secret are wired into McpHttpClient.
        'secret' => env('MCP_SHARED_SECRET'),
        'url' => env('MCP_URL', 'http://localhost:8000/api/mcp'),
    ],

    'google_calendar' => [
        // One shared Google Calendar (not per-user OAuth) — a service
        // account with "Make changes to events" access on this calendar,
        // set up manually in Google's own UI. See GoogleCalendarService.
        // Just a filename — resolved against storage/app/private/ (already
        // gitignored) in AppServiceProvider, so the key file itself is
        // never referenced by a path that could accidentally get committed.
        'credentials_path' => env('GOOGLE_CALENDAR_CREDENTIALS_PATH')
            ? storage_path('app/private/'.env('GOOGLE_CALENDAR_CREDENTIALS_PATH'))
            : null,
        'calendar_id' => env('GOOGLE_CALENDAR_ID'),
        // Deliberately separate from APP_TIMEZONE (which is UTC) — this is
        // the real office timezone events should display in on Google's side.
        'timezone' => env('GOOGLE_CALENDAR_TIMEZONE', 'Asia/Beirut'),
        'reminder_lead_minutes' => env('CALENDAR_REMINDER_LEAD_MINUTES', 15),
    ],

];
