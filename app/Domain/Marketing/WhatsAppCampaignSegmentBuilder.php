<?php

namespace App\Domain\Marketing;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

class WhatsAppCampaignSegmentBuilder
{
    /**
     * @param  array<string, mixed>  $segment
     * @return Collection<int, array{user_id:?int, phone:string, name:?string}>
     */
    public function resolveRecipients(array $segment): Collection
    {

        $phones = [];

        $out = collect();

        $rawPhones = $segment['phones'] ?? [];

        if (is_string($rawPhones)) {

            $rawPhones = preg_split('/[\s,;]+/', $rawPhones) ?: [];

        }

        if (is_array($rawPhones)) {

            foreach ($rawPhones as $p) {

                $p = trim((string) $p);

                if ($p !== '') {

                    $phones[] = $p;

                }

            }

        }

        foreach ($phones as $phone) {

            $out->push(['user_id' => null, 'phone' => $phone, 'name' => null]);

        }

        $query = User::query()->where('role', UserRole::Customer);

        if (! empty($segment['opt_in_only'])) {

            $query->where('whatsapp_opted_out', false);

        }

        if (! empty($segment['city'])) {

            $city = trim((string) $segment['city']);

            $query->whereHas('orders', function ($oq) use ($city): void {

                $oq->whereNot('status', OrderStatus::Cancelled)

                    ->where('shipping_address_snapshot->city', 'like', '%'.$city.'%');

            });

        }

        $days = isset($segment['ordered_within_days']) ? (int) $segment['ordered_within_days'] : 0;

        if ($days > 0) {

            $since = now()->subDays($days)->startOfDay();

            $query->whereHas('orders', function ($oq) use ($since): void {

                $oq->where('created_at', '>=', $since)

                    ->whereNot('status', OrderStatus::Cancelled);

            });

        }

        $categoryId = isset($segment['category_id']) ? (int) $segment['category_id'] : 0;

        if ($categoryId > 0) {
            $query->whereHas('orders', function ($oq) use ($categoryId): void {
                $oq->whereHas('items.variant.product', function ($pq) use ($categoryId): void {
                    $pq->where('category_id', $categoryId);
                });
            });
        }

        $users = $query->whereNotNull('phone')->where('phone', '!=', '')->get(['id', 'name', 'phone', 'whatsapp_opted_out']);

        foreach ($users as $user) {

            if (! empty($segment['opt_in_only']) && $user->whatsapp_opted_out) {

                continue;

            }

            $out->push([

                'user_id' => $user->id,

                'phone' => (string) $user->phone,

                'name' => $user->name,

            ]);

        }

        return $out->unique(fn (array $r) => preg_replace('/\D+/', '', $r['phone']) ?? $r['phone'])->values();

    }

    /**
     * @param  array<string, mixed>  $segment
     */
    public function countPreview(array $segment): int
    {

        return $this->resolveRecipients($segment)->count();

    }
}
