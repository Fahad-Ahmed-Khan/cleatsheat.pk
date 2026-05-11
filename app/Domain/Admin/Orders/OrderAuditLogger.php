<?php

namespace App\Domain\Admin\Orders;

use App\Models\Order;
use App\Models\OrderAuditEvent;
use App\Models\User;

class OrderAuditLogger
{
    public function log(
        Order $order,
        string $eventType,
        ?User $actor,
        ?array $changes = null,
        ?array $meta = null,
    ): void {
        OrderAuditEvent::query()->create([
            'order_id' => $order->id,
            'actor_user_id' => $actor?->id,
            'event_type' => $eventType,
            'changes' => $changes,
            'meta' => $meta,
        ]);
    }
}

