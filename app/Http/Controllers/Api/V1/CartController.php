<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Checkout\CartService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApiCartAddRequest;
use App\Http\Requests\Api\V1\ApiCartUpdateRequest;
use App\Models\CartItem;
use App\Support\Api\ApiResponder;
use App\Support\Api\SanctumBearerUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            $cartModel = $this->resolveCart($request);
        } catch (InvalidArgumentException $e) {
            return ApiResponder::error($e->getMessage(), 422, code: 'cart_invalid');
        }

        return ApiResponder::ok($this->cart->buildCartPayload($cartModel));
    }

    public function store(ApiCartAddRequest $request): JsonResponse
    {
        try {
            $cartModel = $this->resolveCart($request);
        } catch (InvalidArgumentException $e) {
            return ApiResponder::error($e->getMessage(), 422, code: 'cart_invalid');
        }

        $data = $request->validated();

        try {
            $this->cart->addLine(
                $cartModel,
                (int) $data['product_variant_id'],
                (string) $data['size_label'],
                (int) $data['quantity'],
                SanctumBearerUser::resolve($request),
                $data['bargain_phone'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponder::error($e->getMessage(), 422, code: 'cart_invalid');
        }

        $cartModel->refresh();

        return ApiResponder::ok($this->cart->buildCartPayload($cartModel));
    }

    public function update(ApiCartUpdateRequest $request, CartItem $cartItem): JsonResponse
    {
        try {
            $cartModel = $this->resolveCart($request);
        } catch (InvalidArgumentException $e) {
            return ApiResponder::error($e->getMessage(), 422, code: 'cart_invalid');
        }

        abort_unless((int) $cartItem->cart_id === (int) $cartModel->id, 404);

        $qty = (int) $request->validated('quantity');
        try {
            $this->cart->updateQuantity($cartItem, $qty);
        } catch (InvalidArgumentException $e) {
            return ApiResponder::error($e->getMessage(), 422, code: 'cart_invalid');
        }

        $cartModel->refresh();

        return ApiResponder::ok($this->cart->buildCartPayload($cartModel));
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        try {
            $cartModel = $this->resolveCart($request);
        } catch (InvalidArgumentException $e) {
            return ApiResponder::error($e->getMessage(), 422, code: 'cart_invalid');
        }

        abort_unless((int) $cartItem->cart_id === (int) $cartModel->id, 404);
        $cartItem->delete();
        $cartModel->refresh();

        return ApiResponder::ok($this->cart->buildCartPayload($cartModel));
    }

    /**
     * @throws InvalidArgumentException
     */
    private function resolveCart(Request $request): \App\Models\Cart
    {
        $guest = $request->header('X-Guest-Cart-Token');

        return $this->cart->getOrCreateCartForApi(SanctumBearerUser::resolve($request), is_string($guest) ? $guest : null);
    }
}
