<?php

namespace App\Domain\Bargain;

use App\Enums\BargainSessionState;
use App\Models\BargainMessage;
use App\Models\BargainSession;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Bargain\OfferExtractor;
use App\Support\Bargain\PhoneNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BargainEngine
{
    public function __construct(
        private readonly OpenAiBargainPolisher $polisher,
        private readonly AiNegotiationResponder $aiResponder,
    ) {}

    public function start(int $variantId, ?User $user, ?string $guestToken, string $customerPhone, string $customerName): BargainSession
    {
        if (! (bool) config('bargain.enabled', true)) {
            throw new \InvalidArgumentException('Bargaining is currently disabled.');
        }

        $normalizedPhone = PhoneNormalizer::normalize($customerPhone);
        if ($normalizedPhone === null) {
            throw new \InvalidArgumentException('Please provide a valid phone number.');
        }

        $customerKey = PhoneNormalizer::customerKey($user, $normalizedPhone);

        $customerName = trim($customerName);
        if ($customerName === '') {
            throw new \InvalidArgumentException('Please provide your name.');
        }

        return DB::transaction(function () use ($variantId, $user, $guestToken, $normalizedPhone, $customerKey, $customerName): BargainSession {
            /** @var ProductVariant $variant */
            $variant = ProductVariant::query()
                ->whereKey($variantId)
                ->lockForUpdate()
                ->firstOrFail();

            $variant->loadMissing(['product', 'color']);

            $policy = BargainPolicy::fromVariant($variant);
            if (! $policy->bargainEnabled) {
                throw new \InvalidArgumentException('Bargaining is not available for this product right now.');
            }

            BargainSession::query()
                ->where('customer_key', $customerKey)
                ->where('product_variant_id', $variant->id)
                ->whereIn('state', [BargainSessionState::Open->value, BargainSessionState::Countered->value])
                ->update(['state' => BargainSessionState::Expired]);

            $session = BargainSession::query()->create([
                'user_id' => $user?->id,
                'guest_token' => $guestToken,
                'customer_phone' => $normalizedPhone,
                'customer_name' => $customerName,
                'customer_key' => $customerKey,
                'product_variant_id' => $variant->id,
                'state' => BargainSessionState::Open,
                'list_price' => $policy->listPrice,
                'current_offer' => null,
                'accepted_price' => null,
                'checkout_token' => null,
                'lock_consumed_at' => null,
                'expires_at' => now()->addMinutes((int) config('bargain.session_ttl_minutes', 30)),
            ]);

            $label = $this->variantLabel($variant);
            $base = DeterministicShopkeeperReply::welcome($customerName, $variant->product->name, $label, $policy->listPrice, 'welcome:'.$session->id);
            $text = $this->naturalizeShopReply(
                draftText: $base,
                contextMessages: [],
                facts: [
                    'list_price' => $policy->listPrice,
                    'current_offer' => null,
                    'last_customer_offer' => null,
                    'customer_name' => $customerName,
                    'product_name' => (string) $variant->product->name,
                    'variant_label' => $label,
                    'color_name' => (string) ($variant->color?->name ?? ''),
                ],
                languageHint: 'roman_urdu',
                pricingCritical: false,
            );

            BargainMessage::query()->create([
                'bargain_session_id' => $session->id,
                'role' => 'assistant',
                'body' => $text,
                'meta' => [
                    'kind' => 'welcome',
                    'list_price' => $policy->listPrice,
                ],
            ]);

            return $session->fresh(['messages']);
        });
    }

    public function sendMessage(BargainSession $session, ?User $user, string $customerPhone, string $message): BargainMessage
    {
        $this->authorize($session, $user, $customerPhone);
        $this->syncExpiry($session);

        if (! in_array($session->state, [BargainSessionState::Open, BargainSessionState::Countered], true)) {
            throw new \InvalidArgumentException('This negotiation is no longer active.');
        }

        return DB::transaction(function () use ($session, $message): BargainMessage {
            /** @var BargainSession $session */
            $session = BargainSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            $this->syncExpiry($session);
            if (! in_array($session->state, [BargainSessionState::Open, BargainSessionState::Countered], true)) {
                throw new \InvalidArgumentException('This negotiation is no longer active.');
            }

            /** @var ProductVariant $variant */
            $variant = ProductVariant::query()->whereKey($session->product_variant_id)->lockForUpdate()->firstOrFail();
            $variant->loadMissing(['product', 'color']);
            $policy = BargainPolicy::fromVariant($variant);
            if (! $policy->bargainEnabled) {
                throw new \InvalidArgumentException('Bargaining is not available for this product right now.');
            }

            $parsedV2 = (new OfferParserV2)->parse($message);
            $parsed = $parsedV2['singleAmountPkr'] ?? OfferExtractor::extractPkrAmount($message);
            /** @var BargainMessage $customerMsg */
            $customerMsg = BargainMessage::query()->create([
                'bargain_session_id' => $session->id,
                'role' => 'customer',
                'body' => $message,
                'meta' => [
                    'parsed_offer' => $parsed,
                    'parsed_offer_v2' => $parsedV2,
                ],
            ]);

            $lastCustomerId = (int) $customerMsg->id;

            $window = ConversationWindow::load($session->id);
            $msgs = $window->messagesChronological;
            $priorMsgs = array_slice($msgs, 0, -1);
            $priorSignals = (new ConversationAnalyzer)->analyze($priorMsgs);
            $intentDetected = (new IntentDetector)->detect($message, $parsed);

            $customerMsg->forceFill([
                'meta' => array_merge(is_array($customerMsg->meta) ? $customerMsg->meta : [], [
                    'parsed_offer' => $parsed,
                    'intent' => $intentDetected['type'],
                    'intent_confidence' => $intentDetected['confidence'],
                    'intent_patterns' => $intentDetected['matched'],
                ]),
            ])->save();

            $pkg = $window->buildAiContextPackage();
            $dialogMem = AssistantDialogMemory::fromMessages($msgs);
            $avoid = $pkg['assistant_phrases']->avoidSnippets;
            $acceptMinConf = (float) config('bargain.intent.accept_min_confidence', 0.72);
            $typoMinConf = (float) config('bargain.intent.offer_typo_min_confidence', 0.82);
            $seedTail = 'msg:'.$session->id.':'.$lastCustomerId;

            if ($intentDetected['type'] === 'exit' && $intentDetected['confidence'] >= 0.85) {
                return $this->performDeclineLocked($session, $variant);
            }

            if ($intentDetected['type'] === 'reject_soft') {
                $stateKey = (new NegotiationStateManager)->deriveState($session, $intentDetected['type']);
                $decision = new NegotiationDecision(
                    allowedAction: 'casual_reply',
                    targetShopOfferPkr: $session->current_offer !== null ? (string) $session->current_offer : null,
                    customerOfferPkr: null,
                    derivedState: $stateKey,
                    listPricePkr: $policy->listPrice,
                    currentShopOfferPkr: $session->current_offer !== null ? (string) $session->current_offer : null,
                    acceptedPricePkr: $session->accepted_price !== null ? (string) $session->accepted_price : null,
                    integrityFloorPkr: $session->customer_integrity_floor !== null ? (string) $session->customer_integrity_floor : null,
                );
                $aiText = $this->aiResponder->respond($pkg['context'], $decision, $message, $pkg['language_hint']);
                $fallback = DeterministicShopkeeperReply::intentRejectSoft($seedTail.':rej', $avoid);
                $text = trim($aiText) !== '' ? $aiText : $this->polisher->polishShopkeeperWithContext($fallback, $pkg['context'], [], $pkg['language_hint']);

                return BargainMessage::query()->create([
                    'bargain_session_id' => $session->id,
                    'role' => 'assistant',
                    'body' => $text,
                    'meta' => [
                        'kind' => 'reject_soft',
                        'list_price' => $policy->listPrice,
                    ],
                ]);
            }

            $treatAsAccept = $intentDetected['type'] === 'accept' && $intentDetected['confidence'] >= $acceptMinConf;

            if ($treatAsAccept) {
                if ($session->current_offer === null) {
                    $base = DeterministicShopkeeperReply::clarifyAcceptNeedOfferLine(
                        null,
                        $policy->listPrice,
                        $seedTail.':clarify',
                        $avoid,
                    );

                    // AI negotiator: clarify without templates if enabled; fallback to template.
                    $aiText = $this->aiResponder->respond(
                        $pkg['context'],
                        new NegotiationDecision(
                            allowedAction: 'needs_amount',
                            targetShopOfferPkr: null,
                            customerOfferPkr: null,
                            derivedState: (new NegotiationStateManager)->deriveState($session, 'accept'),
                            listPricePkr: $policy->listPrice,
                            currentShopOfferPkr: null,
                            acceptedPricePkr: null,
                            integrityFloorPkr: $session->customer_integrity_floor !== null ? (string) $session->customer_integrity_floor : null,
                        ),
                        $message,
                        $pkg['language_hint'],
                    );
                    $text = trim($aiText) !== '' ? $aiText : $this->polisher->polishShopkeeperWithContext($base, $pkg['context'], [], $pkg['language_hint']);

                    return BargainMessage::query()->create([
                        'bargain_session_id' => $session->id,
                        'role' => 'assistant',
                        'body' => $text,
                        'meta' => [
                            'kind' => 'intent_clarify_accept',
                            'list_price' => $policy->listPrice,
                        ],
                    ]);
                }

                $resolved = $this->resolveAcceptExplicitPrice($parsed, $session, $policy);
                if ($resolved === false) {
                    $base = DeterministicShopkeeperReply::clarifyAcceptNeedOfferLine(
                        $session->current_offer !== null ? (string) $session->current_offer : null,
                        $policy->listPrice,
                        $seedTail.':clarify_amount',
                        $avoid,
                    );

                    $aiText = $this->aiResponder->respond(
                        $pkg['context'],
                        new NegotiationDecision(
                            allowedAction: 'accept_prompt',
                            targetShopOfferPkr: $session->current_offer !== null ? (string) $session->current_offer : null,
                            customerOfferPkr: null,
                            derivedState: (new NegotiationStateManager)->deriveState($session, 'accept'),
                            listPricePkr: $policy->listPrice,
                            currentShopOfferPkr: $session->current_offer !== null ? (string) $session->current_offer : null,
                            acceptedPricePkr: null,
                            integrityFloorPkr: $session->customer_integrity_floor !== null ? (string) $session->customer_integrity_floor : null,
                        ),
                        $message,
                        $pkg['language_hint'],
                    );
                    $text = trim($aiText) !== '' ? $aiText : $this->polisher->polishShopkeeperWithContext($base, $pkg['context'], [], $pkg['language_hint']);

                    return BargainMessage::query()->create([
                        'bargain_session_id' => $session->id,
                        'role' => 'assistant',
                        'body' => $text,
                        'meta' => [
                            'kind' => 'intent_clarify_accept',
                            'list_price' => $policy->listPrice,
                        ],
                    ]);
                }

                $explicitForAccept = $resolved === null ? null : $resolved;

                return $this->performAcceptLockedPrice($session, $variant, $policy, $explicitForAccept);
            }

            if (in_array($intentDetected['type'], ['ask_discount', 'ask_best_price'], true)) {
                $stateKey = (new NegotiationStateManager)->deriveState($session, $intentDetected['type']);
                $shopLine = $session->current_offer !== null ? (string) $session->current_offer : null;
                $lastCustomer = $pkg['last_customer_offer'];

                if ($shopLine !== null && $lastCustomer !== null && $lastCustomer !== '') {
                    $fallback = DeterministicShopkeeperReply::discountWithActiveNegotiation(
                        $lastCustomer,
                        $shopLine,
                        $policy->listPrice,
                        $seedTail.':disc_active',
                        $avoid,
                    );
                    $facts = $this->mergeNegotiationFacts([
                        'list_price' => $policy->listPrice,
                        'current_offer' => $shopLine,
                        'last_customer_offer' => $lastCustomer,
                        'customer_name' => $session->customer_name ?? null,
                        'product_name' => (string) $variant->product->name,
                        'variant_label' => $this->variantLabel($variant),
                        'color_name' => (string) ($variant->color?->name ?? ''),
                    ], NegotiationState::fromConversation($session, $msgs, $lastCustomer, $shopLine), $pkg['assistant_phrases'], null);
                    $text = $this->naturalizeShopReply(
                        draftText: $fallback,
                        contextMessages: $pkg['context'],
                        facts: $facts,
                        languageHint: $pkg['language_hint'],
                        pricingCritical: true,
                    );

                    return BargainMessage::query()->create([
                        'bargain_session_id' => $session->id,
                        'role' => 'assistant',
                        'body' => $text,
                        'meta' => [
                            'kind' => 'intent_discount',
                            'list_price' => $policy->listPrice,
                        ],
                    ]);
                }

                $decision = new NegotiationDecision(
                    allowedAction: 'casual_reply',
                    targetShopOfferPkr: $shopLine,
                    customerOfferPkr: $lastCustomer,
                    derivedState: $stateKey,
                    listPricePkr: $policy->listPrice,
                    currentShopOfferPkr: $shopLine,
                    acceptedPricePkr: $session->accepted_price !== null ? (string) $session->accepted_price : null,
                    integrityFloorPkr: $session->customer_integrity_floor !== null ? (string) $session->customer_integrity_floor : null,
                );
                $aiText = $this->aiResponder->respond($pkg['context'], $decision, $message, $pkg['language_hint']);
                $fallback = DeterministicShopkeeperReply::askDiscountOrBestPrice(
                    $intentDetected['type'] === 'ask_best_price',
                    $policy->listPrice,
                    $shopLine,
                    $seedTail.':disc',
                    $avoid,
                );
                $text = trim($aiText) !== ''
                    ? $aiText
                    : $this->naturalizeShopReply($fallback, $pkg['context'], [], $pkg['language_hint'], false);

                return BargainMessage::query()->create([
                    'bargain_session_id' => $session->id,
                    'role' => 'assistant',
                    'body' => $text,
                    'meta' => [
                        'kind' => 'intent_discount',
                        'list_price' => $policy->listPrice,
                    ],
                ]);
            }

            $shouldHandleSmallTalkNow = in_array($intentDetected['type'], ['greeting', 'question', 'confusion', 'casual_chat'], true)
                && ! ($intentDetected['type'] === 'casual_chat' && $pkg['last_customer_offer'] !== null);
            if ($shouldHandleSmallTalkNow) {
                $stateKey = (new NegotiationStateManager)->deriveState($session, $intentDetected['type']);
                $decision = new NegotiationDecision(
                    allowedAction: $intentDetected['type'] === 'question' ? 'answer_question' : 'casual_reply',
                    targetShopOfferPkr: null,
                    customerOfferPkr: null,
                    derivedState: $stateKey,
                    listPricePkr: $policy->listPrice,
                    currentShopOfferPkr: $session->current_offer !== null ? (string) $session->current_offer : null,
                    acceptedPricePkr: $session->accepted_price !== null ? (string) $session->accepted_price : null,
                    integrityFloorPkr: $session->customer_integrity_floor !== null ? (string) $session->customer_integrity_floor : null,
                );
                $aiText = $this->aiResponder->respond($pkg['context'], $decision, $message, $pkg['language_hint']);
                $fallback = $intentDetected['type'] === 'greeting'
                    ? DeterministicShopkeeperReply::intentGreetingShort($seedTail.':hi', $avoid)
                    : ($intentDetected['type'] === 'confusion'
                        ? DeterministicShopkeeperReply::intentConfusedClarify($policy->listPrice, $session->current_offer !== null ? (string) $session->current_offer : null, $seedTail.':conf', $avoid)
                        : DeterministicShopkeeperReply::askForOfferWithAmount($policy->listPrice, $seedTail.':ask', $avoid, $dialogMem->budgetPromptCount));
                $text = trim($aiText) !== '' ? $aiText : $this->polisher->polishShopkeeperWithContext($fallback, $pkg['context'], [], $pkg['language_hint']);

                return BargainMessage::query()->create([
                    'bargain_session_id' => $session->id,
                    'role' => 'assistant',
                    'body' => $text,
                    'meta' => [
                        'kind' => 'ai_reply',
                        'intent' => $intentDetected['type'],
                        'list_price' => $policy->listPrice,
                    ],
                ]);
            }

            $stated = $parsed;
            if ($stated !== null) {
                $recent = $this->recentCustomerOfferAmountsFromWindow($msgs);
                $shopLine = $session->current_offer !== null ? (string) $session->current_offer : null;
                $correction = (new ContextualOfferCorrector)->correct($stated, $policy->listPrice, $shopLine, $recent);
                if ($correction->corrected !== null && $correction->confidence >= $typoMinConf) {
                    $stated = $correction->corrected;
                    $customerMsg->forceFill([
                        'meta' => array_merge(is_array($customerMsg->meta) ? $customerMsg->meta : [], [
                            'offer_corrected' => $correction->corrected,
                            'offer_correction_confidence' => $correction->confidence,
                        ]),
                    ])->save();
                }
            }

            if ($stated === null) {
                $state = NegotiationState::fromConversation(
                    $session,
                    $msgs,
                    null,
                    $session->current_offer !== null ? (string) $session->current_offer : null,
                );
                $lastCustomerOffer = $pkg['last_customer_offer'];
                $base = $lastCustomerOffer !== null
                    ? DeterministicShopkeeperReply::nudgeIncreaseFromLastOffer($lastCustomerOffer, $seedTail.':nudge', $avoid)
                    : DeterministicShopkeeperReply::askForOfferWithAmount($policy->listPrice, $seedTail.':ask', $avoid, $dialogMem->budgetPromptCount);
                $facts = $this->mergeNegotiationFacts([
                    'list_price' => $policy->listPrice,
                    'current_offer' => $session->current_offer !== null ? (string) $session->current_offer : null,
                    'last_customer_offer' => $lastCustomerOffer,
                    'customer_name' => $session->customer_name ?? null,
                    'product_name' => (string) $variant->product->name,
                    'variant_label' => $this->variantLabel($variant),
                    'color_name' => (string) ($variant->color?->name ?? ''),
                ], $state, $pkg['assistant_phrases'], null);
                $text = $this->polisher->polishShopkeeperWithContext(
                    draftText: $base,
                    contextMessages: $pkg['context'],
                    facts: $facts,
                    languageHint: $pkg['language_hint'],
                );

                return BargainMessage::query()->create([
                    'bargain_session_id' => $session->id,
                    'role' => 'assistant',
                    'body' => $text,
                    'meta' => [
                        'kind' => 'needs_amount',
                        'list_price' => $policy->listPrice,
                    ],
                ]);
            }

            // Compare the customer's stated amount to the floor before clamping up to min:
            // otherwise e.g. "PKR 850" would clamp to 900 and be treated as an acceptable offer.
            if (bccomp($stated, $policy->listPrice, 2) === 1) {
                $stated = $policy->listPrice;
            }

            $signalsFull = (new ConversationAnalyzer)->analyze($msgs);
            $this->recordPricedCustomerTurnMemory($session, $stated);

            if (bccomp($stated, $policy->minAllowedPrice, 2) === -1) {
                $prevShopLine = $session->current_offer !== null ? (string) $session->current_offer : null;
                $lastCustomerId = (int) BargainMessage::query()
                    ->where('bargain_session_id', $session->id)
                    ->where('role', 'customer')
                    ->orderByDesc('id')
                    ->value('id');
                $seedMaterial = 'bargain:'.$session->id.':'.$lastCustomerId;

                $integrity = $session->customer_integrity_floor !== null ? (string) $session->customer_integrity_floor : null;
                $resistanceScore = NegotiationResistance::scoreFromShopLine($prevShopLine ?? $policy->listPrice, $policy);
                $ctx = new ConcessionContext(
                    concessionCount: (int) $session->concession_count,
                    resistanceScore: $resistanceScore,
                    sameOfferStreakAtEnd: $signalsFull->sameOfferStreakAtEnd,
                    stubbornCustomerMode: (bool) $session->stubborn_customer_mode,
                    integrityFloorPkr: $integrity,
                );

                $counterCalc = $policy->steppedCounterBelowMin($prevShopLine, $seedMaterial, $ctx);
                $counter = $this->clampShopLineWithIntegrity($counterCalc, $integrity, $prevShopLine);
                $counter = $this->applyConcessionCooldownIfNeeded($session, $prevShopLine, $counter);

                if ($this->shouldForceHoldFirmPlateau($session, $signalsFull, $resistanceScore, $prevShopLine)
                    && $prevShopLine !== null
                    && bccomp($counter, $prevShopLine, 2) === -1) {
                    $counter = $prevShopLine;
                }

                $noDecrease = $prevShopLine !== null && bccomp($counter, $prevShopLine, 2) >= 0;
                $useDefend = $noDecrease
                    && NegotiationToneDetector::shouldDefendMicroPush($message, $stated, $prevShopLine ?? $counter);

                if ($noDecrease && $useDefend) {
                    $counter = $prevShopLine ?? $counter;
                }

                if (bccomp($counter, $stated, 2) === 1) {
                    $this->mergeIntegrityFloorMax($session, $stated);
                }

                $session->current_offer = $counter;
                $session->state = BargainSessionState::Countered;
                $this->persistShopLineEconomics($session, $prevShopLine, $counter);
                $session->resistance_score = NegotiationResistance::scoreFromShopLine($counter, $policy);
                $session->stubborn_customer_mode = $this->computeStubbornCustomerMode($session, $signalsFull);
                $session->save();

                $window = ConversationWindow::load($session->id);
                $pkg = $window->buildAiContextPackage();
                $msgs = $window->messagesChronological;
                $state = NegotiationState::fromConversation($session, $msgs, $stated, $counter);
                $avoid = $pkg['assistant_phrases']->avoidSnippets;

                if ($noDecrease && $useDefend) {
                    $base = DeterministicShopkeeperReply::defendHoldLine(
                        $counter,
                        $policy->listPrice,
                        $seedMaterial,
                        $avoid,
                    );
                    $metaKind = 'counter_defend';
                } elseif ($noDecrease) {
                    $base = DeterministicShopkeeperReply::counterHoldFirm(
                        $counter,
                        $stated,
                        $policy->listPrice,
                        $seedMaterial,
                        $avoid,
                    );
                    $metaKind = 'counter_hold';
                } else {
                    $base = DeterministicShopkeeperReply::generateCounterReply(
                        $state,
                        $state->signals,
                        $stated,
                        $counter,
                        $policy->listPrice,
                        $seedMaterial,
                        $avoid,
                    );
                    $metaKind = 'counter';
                }

                $facts = $this->mergeNegotiationFacts([
                    'list_price' => $policy->listPrice,
                    'current_offer' => $counter,
                    'last_customer_offer' => $pkg['last_customer_offer'],
                    'customer_name' => $session->customer_name ?? null,
                    'product_name' => (string) $variant->product->name,
                    'variant_label' => $this->variantLabel($variant),
                    'color_name' => (string) ($variant->color?->name ?? ''),
                ], $state, $pkg['assistant_phrases'], null);
                $text = $this->naturalizeShopReply(
                    draftText: $base,
                    contextMessages: $pkg['context'],
                    facts: $facts,
                    languageHint: $pkg['language_hint'],
                    pricingCritical: true,
                );

                return BargainMessage::query()->create([
                    'bargain_session_id' => $session->id,
                    'role' => 'assistant',
                    'body' => $text,
                    'meta' => [
                        'kind' => $metaKind,
                        'customer_offer' => $stated,
                        'counter_offer' => $counter,
                        'list_price' => $policy->listPrice,
                    ],
                ]);
            }

            $offer = $policy->clampToAllowedRange($stated);
            $prevShopLine = $session->current_offer !== null ? (string) $session->current_offer : null;
            $nudged = $policy->nudgeInRangeStatedPrice($offer);

            $integrity = $session->customer_integrity_floor !== null ? (string) $session->customer_integrity_floor : null;
            if ($integrity !== null && bccomp($nudged, $integrity, 2) === -1) {
                $nudged = $integrity;
            }

            if ($prevShopLine !== null && $prevShopLine !== '' && bccomp($prevShopLine, '0', 2) === 1) {
                // Hard rule: shop offers must never increase within a session.
                // This prevents the "you said 11600, then we replied 11767" frustration.
                if (bccomp($nudged, $prevShopLine, 2) === 1) {
                    $nudged = $prevShopLine;
                }

                // If the customer is already willing to meet/beat our last offer,
                // keep the best (lowest) shop offer on the table.
                if (bccomp($offer, $prevShopLine, 2) >= 0) {
                    $nudged = $prevShopLine;
                }
            }

            $nudgeApplied = bccomp($nudged, $offer, 2) === 1;
            if ($nudgeApplied) {
                $this->mergeIntegrityFloorMax($session, $offer);
            }

            $session->current_offer = $nudged;
            $session->state = BargainSessionState::Countered;
            $this->persistShopLineEconomics($session, $prevShopLine, $nudged);
            $session->resistance_score = NegotiationResistance::scoreFromShopLine($nudged, $policy);
            $session->stubborn_customer_mode = $this->computeStubbornCustomerMode($session, $signalsFull);
            $session->save();

            $lastCustomerId = (int) BargainMessage::query()
                ->where('bargain_session_id', $session->id)
                ->where('role', 'customer')
                ->orderByDesc('id')
                ->value('id');
            $seedTail = 'in_range:'.$session->id.':'.$lastCustomerId;
            $window = ConversationWindow::load($session->id);
            $pkg = $window->buildAiContextPackage();
            $msgs = $window->messagesChronological;
            $state = NegotiationState::fromConversation($session, $msgs, $offer, $nudged);
            $avoid = $pkg['assistant_phrases']->avoidSnippets;
            $base = $nudgeApplied
                ? DeterministicShopkeeperReply::acceptableNudged($offer, $nudged, $policy->listPrice, $seedTail, $avoid)
                : DeterministicShopkeeperReply::acceptable($nudged, $seedTail, $avoid);
            $facts = $this->mergeNegotiationFacts([
                'list_price' => $policy->listPrice,
                'current_offer' => $nudged,
                'last_customer_offer' => $pkg['last_customer_offer'],
                'customer_name' => $session->customer_name ?? null,
                'product_name' => (string) $variant->product->name,
                'variant_label' => $this->variantLabel($variant),
                'color_name' => (string) ($variant->color?->name ?? ''),
            ], $state, $pkg['assistant_phrases'], null);
            $text = $this->naturalizeShopReply(
                draftText: $base,
                contextMessages: $pkg['context'],
                facts: $facts,
                languageHint: $pkg['language_hint'],
                pricingCritical: true,
            );

            return BargainMessage::query()->create([
                'bargain_session_id' => $session->id,
                'role' => 'assistant',
                'body' => $text,
                'meta' => [
                    'kind' => $nudgeApplied ? 'acceptable_nudge' : 'acceptable',
                    'customer_offer' => $offer,
                    'counter_offer' => $nudged,
                    'list_price' => $policy->listPrice,
                ],
            ]);
        });
    }

    public function accept(BargainSession $session, ?User $user, string $customerPhone, ?string $acceptedPrice): BargainSession
    {
        $this->authorize($session, $user, $customerPhone);
        $this->syncExpiry($session);

        if (! in_array($session->state, [BargainSessionState::Open, BargainSessionState::Countered], true)) {
            throw new \InvalidArgumentException('This negotiation can’t be accepted anymore.');
        }

        return DB::transaction(function () use ($session, $acceptedPrice): BargainSession {
            /** @var BargainSession $session */
            $session = BargainSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->syncExpiry($session);
            if (! in_array($session->state, [BargainSessionState::Open, BargainSessionState::Countered], true)) {
                throw new \InvalidArgumentException('This negotiation can’t be accepted anymore.');
            }

            /** @var ProductVariant $variant */
            $variant = ProductVariant::query()->whereKey($session->product_variant_id)->lockForUpdate()->firstOrFail();
            $variant->loadMissing(['product', 'color']);
            $policy = BargainPolicy::fromVariant($variant);
            if (! $policy->bargainEnabled) {
                throw new \InvalidArgumentException('Bargaining is not available for this product right now.');
            }

            $this->performAcceptLockedPrice($session, $variant, $policy, $acceptedPrice);

            return $session->fresh(['messages']);
        });
    }

    public function decline(BargainSession $session, ?User $user, string $customerPhone): BargainSession
    {
        $this->authorize($session, $user, $customerPhone);
        $this->syncExpiry($session);

        if (! in_array($session->state, [BargainSessionState::Open, BargainSessionState::Countered], true)) {
            throw new \InvalidArgumentException('This negotiation is already closed.');
        }

        return DB::transaction(function () use ($session): BargainSession {
            /** @var BargainSession $session */
            $session = BargainSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->syncExpiry($session);
            if (! in_array($session->state, [BargainSessionState::Open, BargainSessionState::Countered], true)) {
                throw new \InvalidArgumentException('This negotiation is already closed.');
            }

            /** @var ProductVariant $variant */
            $variant = ProductVariant::query()->whereKey($session->product_variant_id)->lockForUpdate()->firstOrFail();
            $variant->loadMissing(['product', 'color']);

            $this->performDeclineLocked($session, $variant);

            return $session->fresh(['messages']);
        });
    }

    public function status(BargainSession $session, ?User $user, string $customerPhone): BargainSession
    {
        $this->authorize($session, $user, $customerPhone);
        $this->syncExpiry($session);

        $session->load([
            'messages' => fn ($q) => $q->orderByDesc('id')->limit(50),
        ]);

        return $session;
    }

    private function authorize(BargainSession $session, ?User $user, string $customerPhone): void
    {
        $normalized = PhoneNormalizer::normalize($customerPhone);
        if ($normalized === null) {
            throw new \InvalidArgumentException('Please provide a valid phone number.');
        }

        if ($session->user_id !== null) {
            if ($user === null || (int) $user->id !== (int) $session->user_id) {
                throw new AuthorizationException('You are not allowed to access this bargaining session.');
            }
        }

        if ((string) $session->customer_phone !== $normalized) {
            throw new AuthorizationException('You are not allowed to access this bargaining session.');
        }
    }

    private function syncExpiry(BargainSession $session): void
    {
        if ($session->expires_at === null) {
            return;
        }

        if ($session->expires_at->isFuture()) {
            return;
        }

        if (in_array($session->state, [BargainSessionState::Open, BargainSessionState::Countered], true)) {
            $session->state = BargainSessionState::Expired;
            $session->save();

            return;
        }

        if ($session->state === BargainSessionState::Accepted && $session->lock_consumed_at === null) {
            $session->state = BargainSessionState::Expired;
            $session->save();
        }
    }

    private function variantLabel(ProductVariant $variant): string
    {
        $variant->loadMissing(['product', 'color']);

        $color = (string) ($variant->color?->name ?? 'Variant');

        return $color.' · '.$variant->sku;
    }

    private function performAcceptLockedPrice(BargainSession $session, ProductVariant $variant, BargainPolicy $policy, ?string $acceptedPrice): BargainMessage
    {
        if ($acceptedPrice !== null) {
            $explicit = number_format((float) $acceptedPrice, 2, '.', '');
            if (bccomp($explicit, $policy->listPrice, 2) === 1) {
                $explicit = $policy->listPrice;
            }
            if (! $policy->isAllowedPrice($explicit)) {
                throw new \InvalidArgumentException('That price isn’t allowed for this product.');
            }
            $chosen = $explicit;
        } else {
            $chosen = $session->current_offer !== null ? (string) $session->current_offer : null;
            if ($chosen === null) {
                throw new \InvalidArgumentException('There is no offer to accept yet — make an offer first.');
            }
            $chosen = $policy->clampToAllowedRange($chosen);
            if (! $policy->isAllowedPrice($chosen)) {
                throw new \InvalidArgumentException('That price isn’t allowed for this product.');
            }
        }

        $floor = $session->customer_integrity_floor !== null ? (string) $session->customer_integrity_floor : null;
        if ($floor !== null && bccomp($chosen, $floor, 2) === -1) {
            $chosen = $floor;
        }
        if (bccomp($chosen, $policy->minAllowedPrice, 2) === -1) {
            $chosen = $policy->minAllowedPrice;
        }
        $chosen = $policy->clampToAllowedRange($chosen);
        if (! $policy->isAllowedPrice($chosen)) {
            throw new \InvalidArgumentException('That price isn’t allowed for this product.');
        }

        BargainSession::query()
            ->where('customer_key', $session->customer_key)
            ->where('product_variant_id', $session->product_variant_id)
            ->where('id', '!=', $session->id)
            ->where('state', BargainSessionState::Accepted)
            ->whereNull('lock_consumed_at')
            ->update([
                'lock_consumed_at' => now(),
                'state' => BargainSessionState::Consumed,
                'checkout_token' => null,
            ]);

        $session->accepted_price = $chosen;
        $session->checkout_token = Str::random(48);
        $session->state = BargainSessionState::Accepted;
        $session->expires_at = now()->addMinutes((int) config('bargain.lock_ttl_minutes', 60));
        $session->save();

        $window = ConversationWindow::load($session->id);
        $pkg = $window->buildAiContextPackage();
        $msgs = $window->messagesChronological;
        $state = NegotiationState::fromConversation($session, $msgs, $chosen, $chosen);
        $base = DeterministicShopkeeperReply::acceptLockDraft(
            $chosen,
            'accept:'.$session->id.':'.(string) $session->checkout_token,
            $pkg['assistant_phrases']->avoidSnippets,
        );
        $facts = $this->mergeNegotiationFacts([
            'list_price' => $policy->listPrice,
            'current_offer' => $session->current_offer !== null ? (string) $session->current_offer : null,
            'last_customer_offer' => $pkg['last_customer_offer'],
            'customer_name' => $session->customer_name ?? null,
            'product_name' => (string) $variant->product->name,
            'variant_label' => $this->variantLabel($variant),
            'color_name' => (string) ($variant->color?->name ?? ''),
        ], $state, $pkg['assistant_phrases'], null);
        $text = $this->polisher->polishShopkeeperWithContext(
            draftText: $base,
            contextMessages: $pkg['context'],
            facts: $facts,
            languageHint: $pkg['language_hint'],
        );

        return BargainMessage::query()->create([
            'bargain_session_id' => $session->id,
            'role' => 'assistant',
            'body' => $text,
            'meta' => [
                'kind' => 'accepted',
                'accepted_price' => $chosen,
                'checkout_token' => $session->checkout_token,
                'lock_expires_at' => $session->expires_at?->toIso8601String(),
            ],
        ]);
    }

    private function performDeclineLocked(BargainSession $session, ProductVariant $variant): BargainMessage
    {
        $session->state = BargainSessionState::Declined;
        $session->current_offer = null;
        $session->save();

        $window = ConversationWindow::load($session->id);
        $pkg = $window->buildAiContextPackage();
        $msgs = $window->messagesChronological;
        $state = NegotiationState::fromConversation($session, $msgs, null, null);
        $base = DeterministicShopkeeperReply::decline('decline:'.$session->id, $pkg['assistant_phrases']->avoidSnippets);
        $facts = $this->mergeNegotiationFacts([
            'list_price' => (string) $session->list_price,
            'current_offer' => null,
            'last_customer_offer' => $pkg['last_customer_offer'],
            'customer_name' => $session->customer_name ?? null,
            'product_name' => (string) $variant->product->name,
            'variant_label' => $this->variantLabel($variant),
            'color_name' => (string) ($variant->color?->name ?? ''),
        ], $state, $pkg['assistant_phrases'], null);
        $text = $this->polisher->polishShopkeeperWithContext(
            draftText: $base,
            contextMessages: $pkg['context'],
            facts: $facts,
            languageHint: $pkg['language_hint'],
        );

        return BargainMessage::query()->create([
            'bargain_session_id' => $session->id,
            'role' => 'assistant',
            'body' => $text,
            'meta' => [
                'kind' => 'declined',
            ],
        ]);
    }

    /**
     * @return string|false|null chosen explicit PKR, false if message amount blocks accept, null to use session line
     */
    private function resolveAcceptExplicitPrice(?string $parsed, BargainSession $session, BargainPolicy $policy): string|false|null
    {
        if ($parsed === null) {
            return null;
        }

        $explicit = number_format((float) $parsed, 2, '.', '');
        if (bccomp($explicit, $policy->listPrice, 2) === 1) {
            $explicit = $policy->listPrice;
        }
        $explicit = $policy->clampToAllowedRange($explicit);
        if (! $policy->isAllowedPrice($explicit)) {
            return false;
        }

        $line = $session->current_offer !== null ? (string) $session->current_offer : null;
        if ($line !== null && bccomp($explicit, $line, 2) === -1) {
            return false;
        }

        return $explicit;
    }

    /**
     * @param  list<array{id?:int, role:string, body:string, meta?:array<string, mixed>}>  $messagesChronological
     * @return list<string>
     */
    private function recentCustomerOfferAmountsFromWindow(array $messagesChronological): array
    {
        $out = [];
        foreach ($messagesChronological as $m) {
            if (($m['role'] ?? '') !== 'customer') {
                continue;
            }
            $meta = is_array($m['meta'] ?? null) ? $m['meta'] : [];
            $p = $meta['parsed_offer'] ?? null;
            if (is_string($p) && $p !== '') {
                $out[] = $p;
            }
        }

        return $out;
    }

    /**
     * @param  array{context: list<array{role:string, body:string}>, language_hint: string, last_customer_offer: ?string, assistant_phrases: AssistantRecentPhrases}  $pkg
     * @param  list<array{id?:int, role:string, body:string, meta?:array<string, mixed>}>  $msgs
     */
    private function replyIntentAssistant(
        BargainSession $session,
        ProductVariant $variant,
        BargainPolicy $policy,
        array $pkg,
        array $msgs,
        string $kind,
        string $baseDraft,
        ?CustomerIntentResult $intent,
    ): BargainMessage {
        $state = NegotiationState::fromConversation(
            $session,
            $msgs,
            null,
            $session->current_offer !== null ? (string) $session->current_offer : null,
        );
        $facts = $this->mergeNegotiationFacts([
            'list_price' => $policy->listPrice,
            'current_offer' => $session->current_offer !== null ? (string) $session->current_offer : null,
            'last_customer_offer' => $pkg['last_customer_offer'],
            'customer_name' => $session->customer_name ?? null,
            'product_name' => (string) $variant->product->name,
            'variant_label' => $this->variantLabel($variant),
            'color_name' => (string) ($variant->color?->name ?? ''),
        ], $state, $pkg['assistant_phrases'], $intent);
        $text = $this->polisher->polishShopkeeperWithContext(
            draftText: $baseDraft,
            contextMessages: $pkg['context'],
            facts: $facts,
            languageHint: $pkg['language_hint'],
        );

        return BargainMessage::query()->create([
            'bargain_session_id' => $session->id,
            'role' => 'assistant',
            'body' => $text,
            'meta' => [
                'kind' => $kind,
                'list_price' => $policy->listPrice,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array<string, mixed>
     */
    private function mergeNegotiationFacts(array $facts, NegotiationState $state, AssistantRecentPhrases $phrases, ?CustomerIntentResult $intent = null): array
    {
        $snips = array_slice($phrases->avoidSnippets, 0, 6);

        $out = array_merge($facts, [
            'negotiation_stage' => $state->negotiationStage->value,
            'tone_style' => $state->toneStyle,
            'customer_seriousness' => $state->customerSeriousness,
            'repetition_level' => $state->repetitionLevel,
            'close_probability' => number_format($state->closeProbability, 2, '.', ''),
            'assistant_avoid_snippets' => implode(' || ', $snips),
        ]);

        if ($intent !== null) {
            $out['customer_intent'] = $intent->type->value;
            $out['intent_confidence'] = number_format($intent->confidence, 2, '.', '');
        }

        return $out;
    }

    private function recordPricedCustomerTurnMemory(BargainSession $session, string $stated): void
    {
        $session->negotiation_turn_count = (int) $session->negotiation_turn_count + 1;
        $h = $session->highest_customer_offer_seen;
        if ($h === null || bccomp($stated, (string) $h, 2) === 1) {
            $session->highest_customer_offer_seen = $stated;
        }
        $this->maybeRelaxIntegrityFloor($session, $stated);
    }

    private function maybeRelaxIntegrityFloor(BargainSession $session, string $stated): void
    {
        if (! filter_var(config('bargain.integrity.allow_strategic_floor_relaxation', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        $floor = $session->customer_integrity_floor;
        if ($floor === null) {
            return;
        }
        $floorStr = (string) $floor;
        if (bccomp($stated, $floorStr, 2) !== 1) {
            return;
        }
        $minDelta = (string) config('bargain.integrity.strategic_relax_min_pkr', '500.00');
        if (bccomp(bcsub($stated, $floorStr, 2), $minDelta, 2) < 0) {
            return;
        }
        $minTurns = (int) config('bargain.integrity.strategic_relax_min_negotiation_turns', 4);
        if ((int) $session->negotiation_turn_count < $minTurns) {
            return;
        }
        $session->customer_integrity_floor = null;
    }

    private function mergeIntegrityFloorMax(BargainSession $session, string $amount): void
    {
        $f = $session->customer_integrity_floor !== null ? (string) $session->customer_integrity_floor : null;
        if ($f === null) {
            $session->customer_integrity_floor = $amount;

            return;
        }
        if (bccomp($amount, $f, 2) === 1) {
            $session->customer_integrity_floor = $amount;
        }
    }

    private function clampShopLineWithIntegrity(string $line, ?string $integrityFloor, ?string $prevShopLine): string
    {
        $x = $line;
        if ($integrityFloor !== null && $integrityFloor !== '' && bccomp($x, $integrityFloor, 2) === -1) {
            $x = $integrityFloor;
        }
        if ($prevShopLine !== null && $prevShopLine !== '' && bccomp($prevShopLine, '0', 2) === 1) {
            if (bccomp($x, $prevShopLine, 2) === 1) {
                $x = $prevShopLine;
            }
        }

        return $x;
    }

    private function applyConcessionCooldownIfNeeded(BargainSession $session, ?string $prevShopLine, string $candidate): string
    {
        $mins = (int) config('bargain.concession_cooldown_minutes', 0);
        if ($mins <= 0 || $prevShopLine === null) {
            return $candidate;
        }
        if (bccomp($candidate, $prevShopLine, 2) >= 0) {
            return $candidate;
        }
        $at = $session->last_shop_concession_at;
        if ($at !== null && $at->greaterThan(now()->subMinutes($mins))) {
            return $prevShopLine;
        }

        return $candidate;
    }

    private function shouldForceHoldFirmPlateau(BargainSession $session, ConversationSignals $signals, int $resistanceScore, ?string $prevShopLine): bool
    {
        if ($prevShopLine === null) {
            return false;
        }
        $thr = (int) config('bargain.resistance.hold_firm_score_threshold', 85);
        $streakNeed = (int) config('bargain.resistance.hold_firm_min_same_offer_streak', 2);
        if ($resistanceScore < $thr) {
            return false;
        }

        return $signals->sameOfferStreakAtEnd >= $streakNeed
            || $session->stubborn_customer_mode;
    }

    private function persistShopLineEconomics(BargainSession $session, ?string $prevShop, string $newLine): void
    {
        $low = $session->lowest_shop_offer_given;
        if ($low === null || bccomp($newLine, (string) $low, 2) === -1) {
            $session->lowest_shop_offer_given = $newLine;
        }
        if ($prevShop !== null && bccomp($newLine, $prevShop, 2) === -1) {
            $session->concession_count = (int) $session->concession_count + 1;
            $session->last_shop_concession_at = now();
        }
    }

    private function computeStubbornCustomerMode(BargainSession $session, ConversationSignals $signals): bool
    {
        $streakNeed = (int) config('bargain.stubborn.same_offer_streak', 3);
        $minCons = (int) config('bargain.stubborn.min_concessions_without_customer_up', 5);

        return $signals->sameOfferStreakAtEnd >= $streakNeed
            && (int) $session->concession_count >= $minCons;
    }

    /**
     * @param  array<int, array{role:string, body:string}>  $contextMessages
     * @param  array<string, mixed>  $facts
     * @param  'roman_urdu'|'english'|'mixed'  $languageHint
     */
    private function naturalizeShopReply(
        string $draftText,
        array $contextMessages,
        array $facts,
        string $languageHint,
        bool $pricingCritical,
    ): string {
        // Pricing turns use deterministic drafts + light polish so counters are never dropped.
        if ($pricingCritical) {
            return $this->polisher->polishShopkeeperWithContext(
                draftText: $draftText,
                contextMessages: $contextMessages,
                facts: $facts,
                languageHint: $languageHint,
            );
        }

        if (! filter_var(config('bargain.ai.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return $this->polisher->polishShopkeeperWithContext(
                draftText: $draftText,
                contextMessages: $contextMessages,
                facts: $facts,
                languageHint: $languageHint,
            );
        }

        return $this->polisher->polishShopkeeperWithContext(
            draftText: $draftText,
            contextMessages: $contextMessages,
            facts: $facts,
            languageHint: $languageHint,
        );
    }
}
