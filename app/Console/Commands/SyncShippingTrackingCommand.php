<?php

namespace App\Console\Commands;

use App\Enums\ShipmentStatus;
use App\Jobs\SyncShipmentTrackingJob;
use App\Models\Shipment;
use Illuminate\Console\Command;

class SyncShippingTrackingCommand extends Command
{
    protected $signature = 'shipping:sync-tracking';

    protected $description = 'Queue tracking sync jobs for active shipments';

    public function handle(): int
    {
        $count = 0;

        Shipment::query()
            ->whereIn('status', [ShipmentStatus::Booked, ShipmentStatus::InTransit])
            ->whereNotNull('tracking_number')
            ->orderBy('id')
            ->chunkById(100, function ($shipments) use (&$count): void {
                foreach ($shipments as $shipment) {
                    SyncShipmentTrackingJob::dispatch($shipment->id);
                    $count++;
                }
            });

        $this->info("Queued {$count} tracking sync jobs.");

        return self::SUCCESS;
    }
}
