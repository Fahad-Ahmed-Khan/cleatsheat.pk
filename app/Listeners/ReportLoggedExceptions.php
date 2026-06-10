<?php

namespace App\Listeners;

use Illuminate\Log\Events\MessageLogged;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Reports throwables attached to error-level log context to Sentry when report() was not called explicitly.
 */
class ReportLoggedExceptions
{
    private const REPORT_LEVELS = [
        LogLevel::ERROR,
        LogLevel::CRITICAL,
        LogLevel::ALERT,
        LogLevel::EMERGENCY,
    ];

    public function handle(MessageLogged $event): void
    {
        if (! in_array($event->level, self::REPORT_LEVELS, true)) {
            return;
        }

        $exception = $event->context['exception'] ?? null;

        if (! $exception instanceof Throwable) {
            return;
        }

        if ($event->context['_sentry_reported'] ?? false) {
            return;
        }

        report($exception);
    }
}
