<?php

namespace App\Support\Api;

use Illuminate\Support\Facades\Storage;

/**
 * Resolves a stored profile photo path into a public URL for the mobile app.
 * Absolute URLs are passed through untouched; relative paths are served from
 * the public disk. Returns null when there is no photo (app falls back to
 * initials).
 */
class AvatarUrl
{
    public static function forPhoto(?string $photo): ?string
    {
        if (! $photo) {
            return null;
        }

        if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
            return $photo;
        }

        return Storage::disk('public')->url($photo);
    }
}
