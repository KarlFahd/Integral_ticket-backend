<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authorize {{ $client->name }} - {{ config('app.name', 'Integral Chip') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: #f9fafb;
            color: #111827;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .card {
            width: 100%;
            max-width: 28rem;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
        }
        h1 { font-size: 1.25rem; font-weight: 600; text-align: center; margin: 0 0 0.25rem; }
        .subtitle { font-size: 0.875rem; color: #6b7280; text-align: center; margin: 0 0 1.5rem; }
        .user-box {
            border-radius: 0.375rem;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 0.5rem 0.75rem;
            margin-bottom: 1rem;
        }
        .user-box p { margin: 0; }
        .user-box .label { font-size: 0.75rem; color: #6b7280; margin-bottom: 0.125rem; }
        .user-box .value { font-size: 0.875rem; font-weight: 500; }
        .user-box form { display: inline; margin: 0; }
        .user-box .logout-link {
            background: none;
            border: none;
            padding: 0;
            font-size: 0.8125rem;
            color: #4f46e5;
            text-decoration: underline;
            cursor: pointer;
        }
        ul.scopes { list-style: none; margin: 0 0 1.5rem; padding: 0; }
        ul.scopes li {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #4b5563;
            margin-bottom: 0.375rem;
        }
        ul.scopes li::before {
            content: '';
            width: 0.375rem;
            height: 0.375rem;
            border-radius: 9999px;
            background: #6366f1;
            margin-top: 0.4rem;
            flex-shrink: 0;
        }
        .actions { display: flex; gap: 0.75rem; }
        .actions form { flex: 1; margin: 0; }
        button {
            width: 100%;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            cursor: pointer;
        }
        .btn-cancel { border: 1px solid #d1d5db; background: #fff; color: #374151; }
        .btn-cancel:hover { background: #f9fafb; }
        .btn-approve { border: none; background: #4f46e5; color: #fff; }
        .btn-approve:hover { background: #4338ca; }
    </style>
</head>
<body>
<div class="card">
    <h1>Authorize {{ $client->name }}</h1>
    <p class="subtitle">This will let it use available Integral Chip tools on your behalf.</p>

    <div class="user-box">
        <p class="label">Logged in as</p>
        <p class="value">{{ $user->name }} ({{ '@'.$user->username }})</p>
        <form method="POST" action="{{ route('mcp.logout') }}">
            @csrf
            <input type="hidden" name="redirect_to" value="{{ $request->fullUrl() }}">
            <button type="submit" class="logout-link">Not you? Log out</button>
        </form>
    </div>

    @if(count($scopes) > 0)
        <ul class="scopes">
            @foreach($scopes as $scope)
                <li>{{ $scope->description }}</li>
            @endforeach
        </ul>
    @endif

    <div class="actions">
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="btn-cancel">Cancel</button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="btn-approve">Authorize</button>
        </form>
    </div>
</div>
</body>
</html>
