<?php

namespace App\Domain\Notifications\WhatsApp;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Confirms or cancels a COD order based on a WhatsApp button reply.
 *
 * Returns a short outcome string so the caller (ProcessIncomingWhatsAppJob)
 * can write it to the inbound message audit row.
 */
class OrderConfirmationService
{
    public function confirmByButton(int $orderId, string $fromPhone): string
    {
        return $this->transition($orderId, $fromPhone, action: 'confirm');
    }

    public function cancelByButton(int $orderId, string $fromPhone): string
    {
        return $this->transition($orderId, $fromPhone, action: 'cancel');
    }

    public function confirmManual(Order $order, ?string $note = null): string
    {
        return $this->transition($order->id, $order->shipping_address_snapshot['phone'] ?? '', action: 'confirm', channel: 'manual', note: $note);
    }

    public function cancelManual(Order $order, ?string $note = null): string
    {
        return $this->transition($order->id, $order->shipping_address_snapshot['phone'] ?? '', action: 'cancel', channel: 'manual', note: $note);
    }

    private function transition(int $orderId, string $fromPhone, string $action, string $channel = 'whatsapp_button', ?string $note = null): string
    {
        $outcome = 'no_order';

        DB::transaction(function () use ($orderId, $fromPhone, $action, $channel, &$outcome): void {
            $order = Order::query()->lockForUpdate()->find($orderId);
            if ($order === null) {
                $outcome = 'no_order';

                return;
            }

            if (! $this->phoneMatches($order, $fromPhone) && $channel === 'whatsapp_button') {
                $outcome = 'phone_mismatch';
                Log::warning('whatsapp.confirmation.phone_mismatch', [
                    'order_id' => $order->id,
                    'from_phone' => $fromPhone,
                    'snapshot_phone' => $order->shipping_address_snapshot['phone'] ?? null,
                ]);

                return;
            }

            if ($order->status === OrderStatus::Cancelled) {
                $outcome = 'already_cancelled';

                return;
            }

            if ($action === 'confirm') {
                if ($order->confirmed_at !== null || $order->status === OrderStatus::Confirmed) {
                    $outcome = 'already_confirmed';

                    return;
                }

                $allowed = in_array($order->status, [OrderStatus::Pending, OrderStatus::Processing], true);
                if (! $allowed) {
                    $outcome = 'status_not_pending';

                    return;
                }

                $order->status = OrderStatus::Confirmed;
                $order->awaiting_confirmation = false;
                $order->confirmed_at = now();
                $order->confirmation_channel = $channel;
                $order->save();
                // OrderObserver detects the status change and fires `order_confirmed` WA + COD auto-book.

                $outcome = 'confirmed';

                return;
            }

            $order->status = OrderStatus::Cancelled;
            $order->awaiting_confirmation = false;
            $order->confirmation_channel = $channel;
            $order->save();

            $outcome = 'cancelled';
        });

        return $outcome;
    }

    private function phoneMatches(Order $order, string $fromPhone): bool
    {
        $snapshot = (string) ($order->shipping_address_snapshot['phone'] ?? '');
        if ($snapshot === '' || $fromPhone === '') {
            return false;
        }

        $a = preg_replace('/\D+/', '', $snapshot) ?? '';
        $b = preg_replace('/\D+/', '', $fromPhone) ?? '';

        if ($a === '' || $b === '') {
            return false;
        }

        $a = ltrim($a, '0');
        $b = ltrim($b, '0');

        // Compare last 10 digits — robust against the +92 prefix being missing on either side.
        $aTail = substr($a, -10);
        $bTail = substr($b, -10);

        return $aTail !== '' && $aTail === $bTail;
    }
}
