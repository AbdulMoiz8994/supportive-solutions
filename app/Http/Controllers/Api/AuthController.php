<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Exchange the current (valid) token for a fresh one and revoke the old.
     *
     * The mobile app calls this before the token would otherwise expire so the
     * caregiver is never bounced back to the login screen mid-shift. The request
     * must still carry a valid bearer token — this rotates it, it does not
     * re-authenticate credentials.
     */
    public function refresh(Request $request)
    {
        $user = $request->user();

        // Issue the replacement first, then revoke the token that made this call
        // so a mid-flight failure never leaves the device with no valid token.
        $token = $user->createToken('api-token')->plainTextToken;

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Token refreshed',
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
