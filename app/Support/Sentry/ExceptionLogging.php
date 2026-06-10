<?php

namespace App\Support\Sentry;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Log an error and report the exception to Sentry (for catch blocks that handle the failure).
 */
final class ExceptionLogging
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function report(Throwable $e, string $message, array $context = [], string $level = 'error'): void
    {
        Log::log($level, $message, array_merge($context, [
            'exception' => $e,
            '_sentry_reported' => true,
        ]));

        report($e);
    }
}
