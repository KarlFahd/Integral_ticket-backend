<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in - {{ config('app.name', 'Integral Chip') }}</title>
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
            max-width: 24rem;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
        }
        h1 { font-size: 1.25rem; font-weight: 600; text-align: center; margin: 0 0 0.25rem; }
        .subtitle { font-size: 0.875rem; color: #6b7280; text-align: center; margin: 0 0 1.5rem; }
        .error {
            margin-bottom: 1rem;
            border-radius: 0.375rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem; }
        input[type="text"], input[type="password"] {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }
        .field { margin-bottom: 1rem; }
        button {
            width: 100%;
            border: none;
            border-radius: 0.375rem;
            background: #4f46e5;
            color: #fff;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            cursor: pointer;
        }
        button:hover { background: #4338ca; }
    </style>
</head>
<body>
<div class="card">
    <h1>Integral Chip</h1>
    <p class="subtitle">Log in to continue</p>

    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ url('/mcp-login') }}">
        @csrf
        <div class="field">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" value="{{ old('username') }}" required autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>
        </div>
        <button type="submit">Log in</button>
    </form>
</div>
</body>
</html>
