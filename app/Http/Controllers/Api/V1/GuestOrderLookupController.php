<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Orders\CustomerOrderQueryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApiOrderLookupRequest;
use App\Support\Api\ApiResponder;
use Illuminate\Http\JsonResponse;

class GuestOrderLookupController extends Controller
{
    public function __construct(
        private readonly CustomerOrderQueryService $orders,
    ) {}

    public function __invoke(ApiOrderLookupRequest $request): JsonResponse
    {
        $data = $request->validated();
        $order = $this->orders->findForPublicTracking($data['order_number'], $data['email']);
        if ($order === null) {
            return ApiResponder::error('No order matches that reference and email.', 404, code: 'order_not_found');
        }

        return ApiResponder::ok($this->orders->toTrackingPayload($order));
    }
}
