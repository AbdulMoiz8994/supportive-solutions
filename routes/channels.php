<?php

use App\Models\SecureMessageParticipant;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Private channels the mobile app subscribes to for real-time chat. The
| authorizing user comes from the Sanctum bearer token (see the auth:sanctum
| middleware on the /broadcasting/auth endpoint registered in bootstrap/app.php).
*/

// A single conversation thread: only participants may listen.
Broadcast::channel('conversation.{threadId}', function ($user, int $threadId) {
    return SecureMessageParticipant::query()
        ->where('thread_id', $threadId)
        ->where('user_id', $user->id)
        ->exists();
});

// A user's personal channel (inbox badge / new-thread pings): only the owner.
Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return (int) $user->id === (int) $userId;
});
