<?php

namespace App\Jobs;

use App\Domain\Notifications\WhatsApp\WhatsAppNotifier;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        public readonly int $orderId,
        public readonly string $templateKey,
        public readonly string $audience = 'customer',
        public readonly ?string $overrideRecipient = null,
    ) {
        $this->tries = max(1, (int) config('whatsapp.retry.tries', 5));
        $base = max(15, (int) config('whatsapp.retry.backoff_seconds', 60));
        $this->backoff = [
            $base,
            $base * 2,
            $base * 4,
            $base * 8,
            $base * 16,
        ];

        $queue = config('whatsapp.queue');
        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }
    }

    public function handle(WhatsAppNotifier $notifier): void
    {
        $order = Order::query()->find($this->orderId);
        if ($order === null) {
            return;
        }

        $notifier->send($order, $this->templateKey, $this->audience, $this->overrideRecipient);
    }
}
