<?php

namespace App\Domain\Shipping\Pickup;

use App\Domain\Notifications\WhatsApp\TemplateRepository;
use App\Domain\Notifications\WhatsApp\WhatsAppNotifier;
use App\Enums\ShipmentStatus;
use App\Models\Courier;
use App\Models\CourierRider;
use App\Models\NotificationLog;
use App\Models\PickupDispatch;
use App\Models\Shipment;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTemplate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Builds and sends the daily "please pick X parcels today" message to the
 * primary rider of each courier company.
 *
 * Idempotency: we record every send in `pickup_dispatches` and refuse to
 * auto-send twice for the same (courier, date). Manual sends bypass that lock
 * but still record a row so admins can audit duplicates.
 */
class PickupDispatchService
{
    public function __construct(
        private readonly WhatsAppNotifier $notifier,
        private readonly TemplateRepository $templates,
    ) {}

    /**
     * Send daily pickup notices for all couriers that have parcels today and
     * an active primary rider. Returns the persisted dispatch rows.
     *
     * @return array<int, PickupDispatch>
     */
    public function dispatchAllDueToday(?CarbonInterface $date = null, string $sentVia = 'auto'): array
    {
        $settings = WhatsAppSetting::current();
        if (! $settings->enabled_pickup_notices) {
            return [];
        }

        $date = $date ?? Carbon::now();
        $dispatched = [];

        $couriers = Courier::query()->where('is_active', true)->get();
        foreach ($couriers as $courier) {
            $row = $this->dispatchForCourier($courier, $date, $sentVia);
            if ($row !== null) {
                $dispatched[] = $row;
            }
        }

        return $dispatched;
    }

    /**
     * Send the pickup notice for one courier. Returns null when there are no
     * parcels, no rider, or the auto dispatch is already locked for the date.
     */
    public function dispatchForCourier(Courier $courier, ?CarbonInterface $date = null, string $sentVia = 'auto'): ?PickupDispatch
    {
        $date = $date ?? Carbon::now();
        $dateOnly = $date->copy()->startOfDay();

        if ($sentVia === 'auto') {
            $existingAuto = PickupDispatch::query()
                ->where('courier_id', $courier->id)
                ->whereDate('dispatch_date', $dateOnly->toDateString())
                ->where('sent_via', 'auto')
                ->exists();
            if ($existingAuto) {
                return null;
            }
        }

        $shipments = $this->collectShipmentsForCourier($courier, $dateOnly);
        if ($shipments->isEmpty()) {
            return null;
        }

        $rider = $this->resolvePrimaryRider($courier);
        if ($rider === null) {
            return $this->recordSkip($courier, $dateOnly, $sentVia, 'No active primary rider configured.');
        }

        $template = WhatsAppTemplate::findActiveByKey('pickup_notice');
        $bodyTpl = $template?->body ?? "Salaam, please pick {parcels} parcel(s) today. Total COD: PKR {cod_total}. Tracking #s:\n{tracking_list}";

        $trackingList = $shipments->pluck('tracking_number')->filter()->values()->all();
        $codTotal = (float) $shipments->sum(fn (Shipment $s) => (float) ($s->cod_amount ?? 0));

        $body = strtr($bodyTpl, [
            '{parcels}' => (string) $shipments->count(),
            '{cod_total}' => number_format($codTotal, 0, '.', ','),
            '{tracking_list}' => $trackingList === [] ? '—' : implode("\n", array_map(static fn ($t) => '- '.$t, $trackingList)),
            '{courier}' => $courier->name,
            '{name}' => $rider->name,
            '{date}' => $dateOnly->format('M j, Y'),
        ]);

        $logCountBefore = NotificationLog::query()->count();

        $sent = $this->notifier->sendArbitrary(
            recipient: $rider->phone,
            body: $body,
            templateKey: 'pickup_notice',
            audience: 'rider',
        );

        $notificationLog = NotificationLog::query()->latest('id')->first();
        $notificationLogId = ($notificationLog && NotificationLog::query()->count() > $logCountBefore)
            ? $notificationLog->id
            : null;

        return PickupDispatch::query()->create([
            'courier_id' => $courier->id,
            'rider_id' => $rider->id,
            'dispatch_date' => $dateOnly,
            'parcel_count' => $shipments->count(),
            'cod_total' => $codTotal,
            'shipment_ids' => $shipments->pluck('id')->all(),
            'tracking_numbers' => $trackingList,
            'sent_via' => $sentVia,
            'sent_at' => $sent ? now() : null,
            'notification_log_id' => $notificationLogId,
            'status' => $sent ? 'sent' : 'failed',
            'error_message' => $sent ? null : 'WhatsApp send failed (see notification log).',
        ]);
    }

    /**
     * @return Collection<int, Shipment>
     */
    private function collectShipmentsForCourier(Courier $courier, CarbonInterface $date): Collection
    {
        return Shipment::query()
            ->where('courier_id', $courier->id)
            ->whereIn('status', [ShipmentStatus::Booked->value])
            ->whereDate('booked_at', $date->toDateString())
            ->orderBy('id')
            ->get();
    }

    private function resolvePrimaryRider(Courier $courier): ?CourierRider
    {
        return CourierRider::query()
            ->where('courier_id', $courier->id)
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }

    private function recordSkip(Courier $courier, CarbonInterface $date, string $sentVia, string $reason): PickupDispatch
    {
        return PickupDispatch::query()->create([
            'courier_id' => $courier->id,
            'rider_id' => null,
            'dispatch_date' => $date,
            'parcel_count' => 0,
            'cod_total' => 0,
            'shipment_ids' => [],
            'tracking_numbers' => [],
            'sent_via' => $sentVia,
            'sent_at' => null,
            'status' => 'skipped',
            'error_message' => $reason,
        ]);
    }
}
