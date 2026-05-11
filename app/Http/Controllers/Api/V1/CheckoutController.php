<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Checkout\CartService;
use App\Domain\Checkout\CheckoutService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApiCheckoutRequest;
use App\Models\PaymentMethodConfig;
use App\Support\Api\ApiResponder;
use App\Support\Api\SanctumBearerUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CheckoutService $checkout,
    ) {}

    public function paymentMethods(): JsonResponse
    {
        $methods = PaymentMethodConfig::enabledOrdered()->map(fn ($c) => [
            'code' => $c->gateway_code,
            'label' => $c->customer_label,
            'fee_fixed' => (float) $c->fee_fixed,
            'fee_percent' => (float) $c->fee_percent,
        ])->values()->all();

        return ApiResponder::ok($methods);
    }

    public function store(ApiCheckoutRequest $request): JsonResponse
    {
        $guest = $request->header('X-Guest-Cart-Token');
        try {
            $cartModel = $this->cart->getOrCreateCartForApi(SanctumBearerUser::resolve($request), is_string($guest) ? $guest : null);
        } catch (InvalidArgumentException $e) {
            return ApiResponder::error($e->getMessage(), 422, code: 'cart_invalid');
        }

        $data = $request->validated();
        $address = [
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'line1' => $data['line1'],
            'city' => $data['city'],
            'area' => $data['area'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        try {
            $placement = $this->checkout->placeOrder(
                $cartModel,
                $address,
                (string) $data['payment_gateway'],
                SanctumBearerUser::resolve($request),
                $data['guest_email'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponder::error($e->getMessage(), 422, code: 'checkout_invalid');
        }

        $init = $placement->paymentInit;

        return ApiResponder::ok([
            'order_number' => $placement->order->order_number,
            'grand_total' => (float) $placement->order->grand_total,
            'payment_gateway' => $placement->order->payment_gateway,
            'payment_status' => $placement->order->payment_status->value,
            'order_status' => $placement->order->status->value,
            'payment' => [
                'redirect_url' => $init?->redirectUrl,
                'immediate_success' => $init?->immediateSuccess ?? false,
                'meta' => $init?->meta ?? [],
            ],
        ], 201);
    }
}
