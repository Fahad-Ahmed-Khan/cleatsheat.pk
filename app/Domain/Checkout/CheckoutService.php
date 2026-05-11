<?php

namespace App\Domain\Checkout;

use App\Domain\Bargain\PriceLockResolver;
use App\Domain\Payments\CheckoutPlacementResult;
use App\Domain\Payments\PaymentCoordinator;
use App\Domain\Shipping\CourierDispatchService;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethodConfig;
use App\Models\PaymentStatusHistory;
use App\Models\ProductVariant;
use App\Models\ShippingSetting;
use App\Models\User;
use App\Models\VariantSize;
use App\Events\OrderCreated;
use App\Support\Bargain\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CheckoutService
{
    public function __construct(
        private readonly PaymentCoordinator $paymentCoordinator,
        private readonly CourierDispatchService $courierDispatch,
        private readonly PriceLockResolver $priceLocks,
    ) {}

    /**
     * @param  array<string, mixed>  $address
     */
    public function placeOrder(
        Cart $cart,
        array $address,
        string $gatewayCode,
        ?User $user,
        ?string $guestEmail,
    ): CheckoutPlacementResult {
        if ($cart->items()->doesntExist()) {
            throw new InvalidArgumentException('Cart is empty');
        }

        $placement = DB::transaction(function () use ($cart, $address, $gatewayCode, $user, $guestEmail) {
            $cart->load(['items.variant.product.brand', 'items.variant.color']);

            $subtotal = '0';
            foreach ($cart->items as $item) {
                $line = bcmul((string) $item->unit_price_snapshot, (string) $item->quantity, 2);
                $subtotal = bcadd($subtotal, $line, 2);
            }

            $shipping = (string) config('store.shipping_flat', '200');
            $discount = '0';
            $netBeforeFees = bcadd(bcsub($subtotal, $discount, 2), $shipping, 2);

            $codFee = '0';
            $gatewayFee = '0';

            if ($gatewayCode === 'cod') {
                $codFee = (string) config('store.cod_fee', '0');
            } else {
                $cfg = PaymentMethodConfig::query()->where('gateway_code', $gatewayCode)->first();
                if ($cfg !== null) {
                    $gatewayFee = $cfg->feeOnOrderBase($netBeforeFees);
                }
            }

            $grand = bcadd(bcadd($netBeforeFees, $codFee, 2), $gatewayFee, 2);

            $shippingSettings = ShippingSetting::current();

            $orderNumber = $this->uniqueOrderNumber();

            $snapshot = [
                'full_name' => $address['full_name'],
                'phone' => $address['phone'],
                'line1' => $address['line1'],
                'city' => $address['city'],
                'area' => $address['area'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
            ];

            $order = Order::query()->create([
                'order_number' => $orderNumber,
                'user_id' => $user?->id,
                'guest_email' => $guestEmail,
                'guest_token' => null,
                'status' => OrderStatus::Processing,
                'payment_status' => PaymentStatus::Pending,
                'payment_gateway' => $gatewayCode,
                'coupon_id' => null,
                'preferred_courier_id' => $shippingSettings->default_courier_id,
                'courier_assignment' => $shippingSettings->courier_assignment_default,
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'shipping_total' => $shipping,
                'cod_fee' => $codFee,
                'grand_total' => $grand,
                'shipping_address_snapshot' => $snapshot,
                'billing_address_snapshot' => $snapshot,
                'customer_notes' => $address['notes'] ?? null,
            ]);

            PaymentStatusHistory::query()->create([
                'order_id' => $order->id,
                'payment_id' => null,
                'from_status' => null,
                'to_status' => PaymentStatus::Pending->value,
                'source' => 'checkout',
                'message' => 'Order created — awaiting payment.',
            ]);

            foreach ($cart->items as $item) {
                $variant = ProductVariant::query()
                    ->with(['product.brand', 'color'])
                    ->whereKey($item->product_variant_id)
                    ->first();

                if ($variant === null) {
                    throw new InvalidArgumentException('Cart line references a variant that no longer exists.');
                }

                $size = VariantSize::query()
                    ->where('product_variant_id', $variant->id)
                    ->where('size_label', $item->size_label)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($size->stock_qty < $item->quantity) {
                    throw new InvalidArgumentException('Stock changed for '.$variant->sku.' size '.$item->size_label);
                }

                $size->stock_qty -= $item->quantity;
                $size->save();

                $unit = (string) $item->unit_price_snapshot;
                $lineTotal = bcmul($unit, (string) $item->quantity, 2);

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_label' => $variant->color->name,
                    'sku' => $variant->sku,
                    'size_label' => $item->size_label,
                    'quantity' => $item->quantity,
                    'unit_price' => $unit,
                    'line_total' => $lineTotal,
                ]);
            }

            $init = $this->paymentCoordinator->initiateForOrder($order, $gatewayCode);

            $this->courierDispatch->createPendingShipment($order);

            $variantIds = $cart->items->pluck('product_variant_id')->unique()->values()->all();

            $normalizedPhone = PhoneNormalizer::normalize((string) ($address['phone'] ?? ''));
            if ($user !== null) {
                $this->priceLocks->consumeLocksForCheckout($user, null, $variantIds);
            } elseif ($normalizedPhone !== null) {
                $this->priceLocks->consumeLocksForCheckout(null, $normalizedPhone, $variantIds);
            }

            $cart->items()->delete();

            return new CheckoutPlacementResult($order->fresh(['items']), $init);
        });

        Event::dispatch(new OrderCreated($placement->order));

        return $placement;
    }

    private function uniqueOrderNumber(): string
    {
        do {
            $number = 'TR-'.strtoupper(Str::random(10));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
