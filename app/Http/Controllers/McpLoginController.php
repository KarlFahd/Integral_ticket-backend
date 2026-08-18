<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The only session-based ("web" guard) login in this app. It exists purely so
 * Passport's OAuth authorize screen (see AppServiceProvider::boot()) has
 * someone to ask "log in, then approve/deny this connector" — Passport's
 * consent page is a real server-rendered page a browser lands on mid-OAuth
 * redirect, and needs an actual logged-in session to know who's approving.
 *
 * The Vue SPA's own login (AuthController::login) is a completely separate,
 * stateless JSON API and is untouched by this — this controller is reached
 * only via the named `login` route, which Laravel's auth middleware redirects
 * to automatically when a guest hits the OAuth authorize endpoint.
 */
class McpLoginController extends Controller
{
    public function show(): View
    {
        return view('mcp.login');
    }

    public function attempt(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['username' => 'Invalid username or password.'])->onlyInput('username');
        }

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    /**
     * Without this, a browser that already has an active session from
     * connecting one Integral Chip account stays "logged in" for every
     * later OAuth attempt too — Passport's authorize screen just silently
     * reuses that same identity instead of prompting a fresh login, so
     * trying to connect a second account (e.g. testing as an employee after
     * testing as an admin) quietly authorizes the wrong one.
     */
    public function logout(Request $request): RedirectResponse
    {
        // Carry the in-progress OAuth authorize URL across the session reset,
        // so logging out mid-flow and logging back in as someone else lands
        // straight back on a fresh consent screen for the new identity,
        // instead of dumping the user on '/'.
        $redirectTo = $request->input('redirect_to');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($redirectTo) {
            $request->session()->put('url.intended', $redirectTo);
        }

        return redirect()->route('login');
    }
}
