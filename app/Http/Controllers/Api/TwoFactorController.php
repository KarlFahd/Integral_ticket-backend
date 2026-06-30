<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Generate a brand-new secret for this user and return the QR URL.
     * Nothing is saved to the database here — the secret travels back
     * to the frontend and is only committed once the user confirms it.
     */
    public function setup(Request $request): JsonResponse
    {
        $request->validate(['username' => ['required', 'string']]);

        $user = User::where('username', $request->username)->first();

        if ($user === null) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Every user gets a unique random secret — guaranteed different between accounts
        $secret = $this->google2fa->generateSecretKey();

        // The QR URL contains the username so each user's code is distinct in the app
        $qrUrl = $this->google2fa->getQRCodeUrl(
            'Integra Chip',   // issuer / company name
            $user->username,  // account label (Karl, Marc, karlADMIN, …)
            $secret
        );

        return response()->json([
            'secret' => $secret,
            'qr_url' => $qrUrl,
        ]);
    }

    /**
     * The user has scanned the QR code and enters the first code to confirm.
     * Only now do we save the secret and enable 2FA.
     */
    public function enable(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'secret'   => ['required', 'string'],
            'code'     => ['required', 'string', 'digits:6'],
        ]);

        $user = User::where('username', $request->username)->first();

        if ($user === null) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if (! $this->google2fa->verifyKey($request->string('secret'), $request->string('code'))) {
            return response()->json(['message' => 'Invalid code — make sure you scanned the QR code and try again.'], 422);
        }

        $user->totp_secret        = $request->string('secret');
        $user->two_factor_enabled = true;
        $user->save();

        return response()->json(['message' => '2FA enabled successfully.']);
    }

    /**
     * Second login step: verify a live code against the stored secret.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'code'     => ['required', 'string', 'digits:6'],
        ]);

        $user = User::where('username', $request->username)->first();

        if ($user === null || ! $user->two_factor_enabled || $user->totp_secret === null) {
            return response()->json(['message' => '2FA not configured for this user.'], 404);
        }

        if (! $this->google2fa->verifyKey($user->totp_secret, $request->string('code'))) {
            return response()->json(['message' => 'Invalid authenticator code.'], 422);
        }

        return response()->json([
            'user' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'username' => $user->username,
                'is_admin' => (bool) $user->is_admin,
                'is_hr'    => (bool) $user->is_hr,
            ],
        ]);
    }

    /**
     * Disable 2FA and wipe the stored secret for this user.
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate(['username' => ['required', 'string']]);

        $user = User::where('username', $request->username)->first();

        if ($user === null) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->two_factor_enabled = false;
        $user->totp_secret        = null;
        $user->save();

        return response()->json(['message' => '2FA disabled.']);
    }

    /**
     * Return the current 2FA status for a user (so the setup page knows what to show).
     */
    public function status(Request $request): JsonResponse
    {
        $request->validate(['username' => ['required', 'string']]);

        $user = User::where('username', $request->username)->first();

        if ($user === null) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        return response()->json(['enabled' => $user->two_factor_enabled]);
    }
}
