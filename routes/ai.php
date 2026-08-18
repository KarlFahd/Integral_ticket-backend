<?php

use App\Mcp\Servers\IntegralChipServer;
use Laravel\Mcp\Facades\Mcp;

// Registers the OAuth discovery + dynamic client registration endpoints MCP
// clients like Claude.ai expect (/.well-known/oauth-*, /oauth/register),
// backed by Laravel Passport. See app/Http/Controllers/McpLoginController.php
// for the one piece Passport doesn't provide out of the box: an actual login
// page, since this app has no session-based ("web" guard) login otherwise.
Mcp::oauthRoutes();

// The authenticated, full-tool-parity MCP server — same tool set as the
// internal in-app bot (see App\Mcp\Servers\IntegralChipServer's doc comment),
// reachable by any client that completes the OAuth login above. Distinct
// from /api/mcp (internal, shared-secret) and /api/mcp/public (no auth,
// read-only) — neither of those is touched by this.
Mcp::web('/mcp/authed', IntegralChipServer::class)
    ->middleware(['auth:api', 'throttle:30,1']);
