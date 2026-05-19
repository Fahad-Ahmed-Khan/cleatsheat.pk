<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Notifications\WhatsApp\WhatsAppClient;
use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class NotificationLogAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'channel' => $request->input('channel') ?: null,
            'status' => $request->input('status') ?: null,
            'template_key' => $request->input('template_key') ?: null,
            'recipient' => trim((string) $request->input('recipient', '')),
            'date_from' => $request->input('date_from') ?: null,
            'date_to' => $request->input('date_to') ?: null,
            'preset' => $request->input('preset') ?: null,
        ];

        $perPage = (int) $request->input('per_page', 25);
        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 25;
        }

        $logs = NotificationLog::query()
            ->when($filters['channel'], fn ($q, $v) => $q->where('channel', $v))
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['template_key'], fn ($q, $v) => $q->where('template_key', $v))
            ->when($filters['recipient'] !== '', fn ($q) => $q->where('recipient', 'like', '%'.$filters['recipient'].'%'))
            ->when($filters['date_from'], function ($q, $v) {
                try {
                    $q->where('created_at', '>=', Carbon::parse($v)->startOfDay());
                } catch (\Throwable) {
                }
            })
            ->when($filters['date_to'], function ($q, $v) {
                try {
                    $q->where('created_at', '<=', Carbon::parse($v)->endOfDay());
                } catch (\Throwable) {
                }
            })
            ->when($filters['preset'] === 'failed', fn ($q) => $q->where('status', 'failed'))
            ->when($filters['preset'] === 'whatsapp_failed', fn ($q) => $q->where('channel', 'whatsapp')->where('status', 'failed'))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $logs->through(fn (NotificationLog $log): array => [
            'id' => $log->id,
            'channel' => $log->channel,
            'recipient' => $log->recipient,
            'template_key' => $log->template_key,
            'status' => $log->status,
            'error_message' => $log->error_message,
            'payload' => $log->payload,
            'created_at' => $log->created_at?->toIso8601String(),
            'created_at_human' => $log->created_at?->format('M j, Y H:i'),
            'is_retryable' => $log->channel === 'whatsapp' && $log->status === 'failed',
        ]);

        $stats = [
            'total' => NotificationLog::query()->count(),
            'failed' => NotificationLog::query()->where('status', 'failed')->count(),
            'whatsapp_failed' => NotificationLog::query()
                ->where('channel', 'whatsapp')
                ->where('status', 'failed')
                ->count(),
            'sent_24h' => NotificationLog::query()
                ->where('status', 'sent')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];

        $facets = [
            'channels' => NotificationLog::query()->distinct()->orderBy('channel')->pluck('channel')->all(),
            'statuses' => NotificationLog::query()->distinct()->orderBy('status')->pluck('status')->all(),
            'template_keys' => NotificationLog::query()
                ->distinct()
                ->orderBy('template_key')
                ->pluck('template_key')
                ->all(),
        ];

        return Inertia::render('Admin/Notifications/Index', [
            'logs' => $logs,
            'filters' => array_merge($filters, ['per_page' => $perPage]),
            'stats' => $stats,
            'facets' => $facets,
        ]);
    }

    /**
     * Retry a failed WhatsApp notification by re-dispatching the original payload
     * stored on the log. We never mutate the original log row; a new log entry is
     * created with the same template/recipient so the audit trail stays intact.
     */
    public function retry(NotificationLog $notificationLog, WhatsAppClient $client): RedirectResponse
    {
        if ($notificationLog->channel !== 'whatsapp') {
            return back()->with('error', 'Only WhatsApp notifications can be retried from this screen.');
        }

        if ($notificationLog->status !== 'failed') {
            return back()->with('error', 'Only failed notifications can be retried.');
        }

        $payload = is_array($notificationLog->payload) ? $notificationLog->payload : [];
        $request = is_array($payload['request'] ?? null) ? $payload['request'] : null;
        if ($request === null || $request === []) {
            return back()->with('error', 'Cannot retry: original request payload is missing on this log.');
        }

        $cloudEnabled = (bool) config('whatsapp.cloud.enabled', false);

        try {
            $response = $cloudEnabled
                ? $client->sendCloudMessage($request)
                : $client->sendBridgeMessage($request);

            NotificationLog::query()->create([
                'channel' => 'whatsapp',
                'recipient' => $notificationLog->recipient,
                'template_key' => $notificationLog->template_key,
                'payload' => [
                    'audience' => $payload['audience'] ?? null,
                    'request' => $request,
                    'response' => $response,
                    'retry_of' => $notificationLog->id,
                ],
                'status' => 'sent',
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            NotificationLog::query()->create([
                'channel' => 'whatsapp',
                'recipient' => $notificationLog->recipient,
                'template_key' => $notificationLog->template_key,
                'payload' => [
                    'audience' => $payload['audience'] ?? null,
                    'request' => $request,
                    'retry_of' => $notificationLog->id,
                ],
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return back()->with('error', 'WhatsApp retry failed: '.$e->getMessage());
        }

        return back()->with('status', 'WhatsApp notification re-sent.');
    }
}
