<?php

namespace App\Support\Storage;

use Illuminate\Support\Facades\Storage;

/**
 * Resolve public-disk asset paths for the current storage configuration.
 *
 * Legacy rows may store full URLs from local dev (e.g. http://tryino-ecom.test/storage/...).
 * Production may use Backblaze B2 / Cloudflare CDN via Storage::disk('public')->url().
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
            $relative = self::normalizeForStorage($path);
            if ($relative !== null && $relative !== $path && ! preg_match('#^https?://#i', $relative)) {
                return self::publicUrl($relative);
            }

            return $path;
        }

        if (str_starts_with($path, '/storage/')) {
            return self::publicUrl(ltrim(substr($path, strlen('/storage/')), '/'));
        }

        if (str_starts_with($path, 'storage/')) {
            return self::publicUrl(ltrim(substr($path, strlen('storage/')), '/'));
        }

        return self::publicUrl(ltrim($path, '/'));
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
            $relative = self::relativeFromUrl($path);

            return $relative ?? $path;
        }

        if (preg_match('#^/?storage/(.+)$#', $path, $matches)) {
            return $matches[1];
        }

        return ltrim($path, '/');
    }

    private static function relativeFromUrl(string $url): ?string
    {
        $pathPart = parse_url($url, PHP_URL_PATH);
        if (! is_string($pathPart) || $pathPart === '') {
            return null;
        }

        $fromLegacyStorage = self::relativeFromPublicStoragePath($pathPart);
        if ($fromLegacyStorage !== null) {
            return $fromLegacyStorage;
        }

        return self::relativeFromConfiguredPublicBase($url, $pathPart)
            ?? self::relativeFromS3StylePath($pathPart);
    }

    private static function relativeFromPublicStoragePath(string $pathPart): ?string
    {
        if (preg_match('#/storage/(.+)$#', $pathPart, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Strip AWS_URL / CDN base when the URL points at our public disk.
     */
    private static function relativeFromConfiguredPublicBase(string $url, string $pathPart): ?string
    {
        $configuredUrl = config('filesystems.disks.public.url');
        if (! is_string($configuredUrl) || $configuredUrl === '') {
            return null;
        }

        $configuredHost = parse_url($configuredUrl, PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);

        if (! is_string($configuredHost) || ! is_string($urlHost) || strcasecmp($configuredHost, $urlHost) !== 0) {
            return null;
        }

        $configuredPath = parse_url($configuredUrl, PHP_URL_PATH) ?? '';
        $configuredPath = trim($configuredPath, '/');

        $objectPath = ltrim($pathPart, '/');
        if ($configuredPath !== '' && str_starts_with($objectPath, $configuredPath.'/')) {
            $objectPath = substr($objectPath, strlen($configuredPath) + 1);
        }

        return $objectPath !== '' ? $objectPath : null;
    }

    /**
     * Path-style S3 / Backblaze B2 URLs: /bucket-name/object/key
     */
    private static function relativeFromS3StylePath(string $pathPart): ?string
    {
        $bucket = config('filesystems.disks.public.bucket');
        if (! is_string($bucket) || $bucket === '') {
            return null;
        }

        $trimmed = ltrim($pathPart, '/');
        $prefix = $bucket.'/';

        if (str_starts_with($trimmed, $prefix)) {
            $relative = substr($trimmed, strlen($prefix));

            return $relative !== '' ? $relative : null;
        }

        return null;
    }

    private static function publicUrl(string $relative): string
    {
        return Storage::disk('public')->url($relative);
    }
}
