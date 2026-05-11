<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Bargain\BargainEngine;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BargainAcceptRequest;
use App\Http\Requests\Api\V1\BargainDeclineRequest;
use App\Http\Requests\Api\V1\BargainMessageRequest;
use App\Http\Requests\Api\V1\StartBargainSessionRequest;
use App\Models\BargainMessage;
use App\Models\BargainSession;
use App\Models\User;
use App\Support\Api\ApiResponder;
use App\Support\Api\SanctumBearerUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BargainController extends Controller
{
    public function start(Request $request, StartBargainSessionRequest $requestData, BargainEngine $engine): JsonResponse
    {
        try {
            $user = SanctumBearerUser::resolve($request);
            $data = $requestData->validated();

            $guestToken = $data['guest_token'] ?? null;
            $guestToken = is_string($guestToken) && $guestToken !== '' ? $guestToken : null;

            $session = $engine->start(
                (int) $data['product_variant_id'],
                $user,
                $guestToken,
                (string) $data['customer_phone'],
                (string) $data['customer_name'],
            );

            return ApiResponder::ok([
                'session' => $this->serializeSession($session, includeCheckoutToken: false),
            ]);
        } catch (\InvalidArgumentException $e) {
            return ApiResponder::error($e->getMessage(), 422, code: 'bargain_invalid');
        }
    }

    public function message(
        Request $request,
        BargainMessageRequest $requestData,
        BargainSession $session,
        BargainEngine $engine,
    ): JsonResponse {
        try {
            $user = SanctumBearerUser::resolve($request);
            $data = $requestData->validated();

            /** @var BargainMessage $assistant */
            $assistant = $engine->sendMessage(
                $session,
                $user,
                (string) $data['customer_phone'],
                (string) $data['message'],
            );

            $session->refresh();

            return ApiResponder::ok([
                'assistant_message' => $this->serializeMessage($assistant),
                'session' => $this->serializeSession($session, includeCheckoutToken: false),
            ]);
        } catch (\InvalidArgumentException $e) {
            return ApiResponder::error($e->getMessage(), 422, code: 'bargain_invalid');
        } catch (AuthorizationException $e) {
            return ApiResponder::error($e->getMessage(), 403, code: 'bargain_forbidden');
        }
    }

    public function accept(
        Request $request,
        BargainAcceptRequest $requestData,
        BargainSession $session,
        BargainEngine $engine,
    ): JsonResponse {
        try {
            $user = SanctumBearerUser::resolve($request);
            $data = $requestData->validated();

            $price = $data['price'] ?? null;
            $priceStr = $price !== null ? (string) $price : null;

            $session = $engine->accept(
                $session,
                $user,
                (string) $data['customer_phone'],
                $priceStr,
            );

            return ApiResponder::ok([
                'session' => $this->serializeSession($session, includeCheckoutToken: true),
                'tracking' => [
                    'bargain_accepted' => [
                        'currency' => 'PKR',
                        'value' => (float) (string) $session->accepted_price,
                        'product_variant_id' => $session->product_variant_id,
                    ],
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return ApiResponder::error($e->getMessage(), 422, code: 'bargain_invalid');
        } catch (AuthorizationException $e) {
            return ApiResponder::error($e->getMessage(), 403, code: 'bargain_forbidden');
        }
    }

    public function decline(
        Request $request,
        BargainDeclineRequest $requestData,
        BargainSession $session,
        BargainEngine $engine,
    ): JsonResponse {
        try {
            $user = SanctumBearerUser::resolve($request);
            $data = $requestData->validated();

            $session = $engine->decline(
                $session,
                $user,
                (string) $data['customer_phone'],
            );

            return ApiResponder::ok([
                'session' => $this->serializeSession($session, includeCheckoutToken: false),
            ]);
        } catch (\InvalidArgumentException $e) {
            return ApiResponder::error($e->getMessage(), 422, code: 'bargain_invalid');
        } catch (AuthorizationException $e) {
            return ApiResponder::error($e->getMessage(), 403, code: 'bargain_forbidden');
        }
    }

    public function status(Request $request, BargainSession $session, BargainEngine $engine): JsonResponse
    {
        try {
            $user = SanctumBearerUser::resolve($request);
            $data = $request->validate([
                'customer_phone' => ['required', 'string', 'max:32'],
            ]);

            $session = $engine->status($session, $user, (string) $data['customer_phone']);

            return ApiResponder::ok([
                'session' => $this->serializeSession($session, includeCheckoutToken: false),
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponder::error($e->getMessage(), 403, code: 'bargain_forbidden');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSession(BargainSession $session, bool $includeCheckoutToken): array
    {
        $session->loadMissing('messages');

        return [
            'id' => $session->id,
            'product_variant_id' => $session->product_variant_id,
            'state' => $session->state->value,
            'list_price' => (string) $session->list_price,
            'current_offer' => $session->current_offer !== null ? (string) $session->current_offer : null,
            'accepted_price' => $session->accepted_price !== null ? (string) $session->accepted_price : null,
            'customer_name' => $session->customer_name,
            'expires_at' => $session->expires_at?->toIso8601String(),
            'checkout_token' => $includeCheckoutToken ? $session->checkout_token : null,
            'messages' => $session->messages
                ->sortBy('id')
                ->values()
                ->map(fn (BargainMessage $m) => $this->serializeMessage($m))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(BargainMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'body' => $message->body,
            'meta' => $message->meta,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
