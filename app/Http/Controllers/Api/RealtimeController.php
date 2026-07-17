<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Self-service diagnostics for the real-time (Laravel Reverb / Pusher-protocol)
 * chat socket.
 *
 * Why this exists: the #1 cause of the mobile app's "connecting → disconnected →
 * reconnecting" loop is a mismatch between the app-key/host the Flutter client is
 * built with and the values the server actually runs with. When they don't match,
 * Reverb replies `pusher:error {code:4001, "Application does not exist"}` and drops
 * the socket the instant it opens — which the Pusher client silently retries, so
 * the reason never surfaces in the app logs.
 *
 * This endpoint returns the EXACT connection parameters the server expects (never
 * the secret — the app key is public by design, it travels in the socket URL), so
 * the mobile dev can confirm their client config matches character-for-character
 * and tell at a glance whether broadcasting is even switched on for this env.
 */
class RealtimeController extends Controller
{
    /**
     * GET /api/realtime/config
     *
     * Mirror of the values the client must connect with. Compare each field to
     * the app's Pusher/Reverb init. `enabled=false` means this environment is
     * still on the `log` broadcaster (BROADCAST_CONNECTION not set to `reverb`),
     * so no live pushes will ever fire — build against REST polling until it is.
     */
    public function config(Request $request): JsonResponse
    {
        $driver = config('broadcasting.default');
        $reverb = config('broadcasting.connections.reverb');
        $options = $reverb['options'] ?? [];

        return response()->json([
            'enabled' => $driver === 'reverb',
            'driver' => $driver,
            // The app key is public (it is sent in the websocket URL). The secret is never exposed.
            'key' => $reverb['key'] ?? null,
            'host' => $options['host'] ?? null,
            'port' => (int) ($options['port'] ?? 443),
            'scheme' => $options['scheme'] ?? 'https',
            'use_tls' => ($options['scheme'] ?? 'https') === 'https',
            // The client authorizes private channels here (NOT under the /api prefix).
            'auth_endpoint' => url('/broadcasting/auth'),
            // Channel names the app subscribes to for the logged-in user.
            'channels' => [
                'conversation' => 'private-conversation.{threadId}',
                'user' => 'private-user.'.$request->user()->id,
            ],
            'event' => 'message.sent',
            // If key/host/port here do not match your client, you will get a 4001 loop.
            'hint' => $driver === 'reverb'
                ? 'Connect your Pusher-protocol client with the key/host/port/scheme above. A connect→disconnect loop means these do not match (Reverb error 4001).'
                : 'Broadcasting is on the "'.$driver.'" driver in this environment — live sockets are OFF. Use REST polling until BROADCAST_CONNECTION=reverb is set here.',
        ]);
    }
}
