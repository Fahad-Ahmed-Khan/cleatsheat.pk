<?php

namespace Tests\Unit\Sentry;

use App\Listeners\ReportLoggedExceptions;
use App\Support\Sentry\ExceptionLogging;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class ExceptionLoggingTest extends TestCase
{
    public function test_report_logs_exception_with_sentry_flag(): void
    {
        $exception = new RuntimeException('deploy failed');
        $handler = $this->mock(\Illuminate\Contracts\Debug\ExceptionHandler::class);
        $handler->shouldReceive('report')->once()->with($exception);

        Event::fake([MessageLogged::class]);

        ExceptionLogging::report($exception, 'deploy.test_failed', ['branch' => 'production']);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $event) use ($exception): bool {
            return $event->level === 'error'
                && $event->message === 'deploy.test_failed'
                && ($event->context['exception'] ?? null) === $exception
                && ($event->context['_sentry_reported'] ?? false) === true
                && ($event->context['branch'] ?? null) === 'production';
        });
    }

    #[TestWith(['error'])]
    #[TestWith(['critical'])]
    public function test_report_logged_exceptions_listener_reports_throwable_in_context(string $level): void
    {
        $exception = new RuntimeException('listener test');
        $handler = $this->mock(\Illuminate\Contracts\Debug\ExceptionHandler::class);
        $handler->shouldReceive('report')->once()->with($exception);

        (new ReportLoggedExceptions)->handle(new MessageLogged($level, 'job failed', [
            'exception' => $exception,
        ]));
    }

    public function test_report_logged_exceptions_listener_skips_already_reported_logs(): void
    {
        $exception = new RuntimeException('already reported');
        $handler = $this->mock(\Illuminate\Contracts\Debug\ExceptionHandler::class);
        $handler->shouldNotReceive('report');

        (new ReportLoggedExceptions)->handle(new MessageLogged('error', 'job failed', [
            'exception' => $exception,
            '_sentry_reported' => true,
        ]));
    }
}
