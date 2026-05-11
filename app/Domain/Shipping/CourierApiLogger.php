<?php

namespace App\Domain\Shipping;

use App\Models\Courier;
use App\Models\CourierAccount;
use App\Models\CourierApiLog;
use App\Models\Shipment;

class CourierApiLogger
{
    /**
     * @param  array<string, mixed>|null  $requestPayload
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function log(
        ?Courier $courier,
        ?CourierAccount $account,
        ?Shipment $shipment,
        string $direction,
        ?string $endpoint,
        ?int $httpStatus,
        ?array $requestPayload,
        ?array $responsePayload,
        ?string $errorMessage,
        int $attempt = 1,
    ): CourierApiLog {
        return CourierApiLog::query()->create([
            'courier_id' => $courier?->id,
            'courier_account_id' => $account?->id,
            'shipment_id' => $shipment?->id,
            'direction' => $direction,
            'endpoint' => $endpoint,
            'http_status' => $httpStatus,
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'error_message' => $errorMessage,
            'attempt' => $attempt,
        ]);
    }
}
