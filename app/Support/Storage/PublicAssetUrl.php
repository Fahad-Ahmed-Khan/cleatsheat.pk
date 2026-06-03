<?php

namespace App\Support\Storage;

use Illuminate\Support\Facades\Storage;

/**
 * Resolve public-disk asset paths for the current APP_URL.
 *
 * Legacy rows may store full URLs from local dev (e.g. http://tryino-ecom.test/storage/...).
 */
final class PublicAssetUrl
{
    public static function resolve(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (preg_match('#^https?://#i', $path) === 1) {
            $relative = self::relativeFromPublicStoragePath(parse_url($path, PHP_URL_PATH) ?? '');

            return $relative !== null
                ? self::publicUrl($relative)
                : $path;
        }

        if (str_starts_with($path, '/storage/')) {
            return rtrim(config('app.url'), '/').$path;
        }

        if (str_starts_with($path, 'storage/')) {
            return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
        }

        return Storage::disk('public')->url(ltrim($path, '/'));
    }

    /**
     * Store only the disk-relative path (e.g. products/foo.jpg), not a full URL.
     */
    public static function normalizeForStorage(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (preg_match('#^https?://#i', $path) === 1) {
            $relative = self::relativeFromPublicStoragePath(parse_url($path, PHP_URL_PATH) ?? '');

            return $relative ?? $path;
        }

        if (preg_match('#^/?storage/(.+)$#', $path, $matches)) {
            return $matches[1];
        }

        return ltrim($path, '/');
    }

    private static function relativeFromPublicStoragePath(string $pathPart): ?string
    {
        if (preg_match('#/storage/(.+)$#', $pathPart, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private static function publicUrl(string $relative): string
    {
        return rtrim(config('app.url'), '/').'/storage/'.ltrim($relative, '/');
    }
}
