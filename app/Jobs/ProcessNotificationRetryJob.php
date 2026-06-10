<?php

namespace App\Jobs;

use App\Domain\Notifications\WhatsApp\WhatsAppClient;
use App\Models\NotificationLog;
use App\Support\Sentry\ExceptionLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Retry recent failed WhatsApp notifications with exponential backoff.
 *
 * The backoff is enforced via the NotificationLog row itself: we only retry
 * a row whose original failure is at least N minutes old, and we cap automatic
 * retries via `payload.auto_retries` to avoid hammering the carrier.
 */
class ProcessNotificationRetryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MAX_AUTO_RETRIES = 3;

    public function __construct(
        public int $maxBatch = 25,
        public int $minAgeMinutes = 5,
    ) {
        $this->onQueue('default');
    }

    public function handle(WhatsAppClient $client): void
    {
        $logs = NotificationLog::query()
            ->where('channel', 'whatsapp')
            ->where('status', 'failed')
            ->where('created_at', '<=', now()->subMinutes($this->minAgeMinutes))
            ->orderBy('created_at')
            ->limit($this->maxBatch)
            ->get();

        $cloudEnabled = (bool) config('whatsapp.cloud.enabled', false);
        $sent = 0;

        foreach ($logs as $log) {
            $payload = is_array($log->payload) ? $log->payload : [];
            $autoRetries = (int) ($payload['auto_retries'] ?? 0);
            if ($autoRetries >= self::MAX_AUTO_RETRIES) {
                continue;
            }

            $request = is_array($payload['request'] ?? null) ? $payload['request'] : null;
            if ($request === null || $request === []) {
                continue;
            }

            try {
                $response = $cloudEnabled
                    ? $client->sendCloudMessage($request)
                    : $client->sendBridgeMessage($request);

                NotificationLog::query()->create([
                    'channel' => 'whatsapp',
                    'recipient' => $log->recipient,
                    'template_key' => $log->template_key,
                    'payload' => array_merge($payload, [
                        'response' => $response,
                        'auto_retries' => $autoRetries + 1,
                        'retry_of' => $log->id,
                    ]),
                    'status' => 'sent',
                    'error_message' => null,
                ]);

                // Mark the original row's payload so we don't retry it again.
                $log->payload = array_merge($payload, [
                    'auto_retries' => $autoRetries + 1,
                    'retry_succeeded_at' => now()->toIso8601String(),
                ]);
                $log->save();

                $sent++;
            } catch (\Throwable $e) {
                $log->payload = array_merge($payload, [
                    'auto_retries' => $autoRetries + 1,
                    'last_auto_retry_at' => now()->toIso8601String(),
                    'last_auto_retry_error' => $e->getMessage(),
                ]);
                $log->save();

                ExceptionLogging::report($e, 'notifications.whatsapp_auto_retry_failed', [
                    'notification_log_id' => $log->id,
                    'auto_retries' => $autoRetries + 1,
                ]);
            }
        }

        if ($sent > 0) {
            Log::info('notifications.whatsapp_auto_retry', ['sent' => $sent]);
        }
    }
}
