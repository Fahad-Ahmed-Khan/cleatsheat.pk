<?php

namespace App\Support\Deploy;

use Illuminate\Support\Facades\File;

/**
 * Webhook sets a pending marker synchronously (always works from PHP-FPM).
 * `deploy:run-pending` (scheduler/cron) performs the actual pull-deploy when
 * background nohup from the webhook is killed by shared hosting.
 */
final class DeployPendingMarker
{
    private const RELATIVE_PATH = 'framework/deploy-pending.json';

    public static function path(): string
    {
        return storage_path(self::RELATIVE_PATH);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function set(array $meta = []): void
    {
        $dir = dirname(self::path());
        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir);
        }

        $payload = array_merge([
            'requested_at' => now()->toIso8601String(),
            'branch' => (string) config('deploy.branch', 'production'),
        ], $meta);

        file_put_contents(
            self::path(),
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n",
            LOCK_EX
        );
    }

    public static function exists(): bool
    {
        return is_file(self::path());
    }

    public static function clear(): void
    {
        if (is_file(self::path())) {
            unlink(self::path());
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function read(): ?array
    {
        if (! is_file(self::path())) {
            return null;
        }

        $raw = file_get_contents(self::path());
        if ($raw === false || trim($raw) === '') {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }
}
