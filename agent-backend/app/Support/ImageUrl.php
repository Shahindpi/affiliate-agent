<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class ImageUrl
{
    /**
     * Convert a storage path into a public URL.
     */
    public static function make(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // Rebuild legacy local development storage URLs using the current
        // public disk URL instead of preserving an obsolete localhost host.
        if (preg_match('#^https?://(?:localhost|127\.0\.0\.1)(?::\d+)?/storage/(.+)$#i', $path, $matches)) {
            return Storage::disk('public')->url($matches[1]);
        }

        // Already a full non-local URL
        if (
            str_starts_with($path, 'http://') ||
            str_starts_with($path, 'https://')
        ) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
