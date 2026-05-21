<?php

namespace App\Jobs;

use App\Domain\Marketing\WhatsAppCampaignSender;
use App\Domain\Notifications\WhatsApp\WhatsAppNotifier;
use App\Models\Order;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCampaignBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $campaignId) {}

    public function handle(WhatsAppCampaignSender $sender, WhatsAppNotifier $notifier): void
    {

        $campaign = WhatsAppCampaign::query()->with('template')->find($this->campaignId);

        if ($campaign === null || ! in_array($campaign->status, ['sending', 'scheduled'], true)) {

            return;

        }

        if ($campaign->status === 'scheduled') {

            $campaign->status = 'sending';

            $campaign->save();

        }

        $limit = $sender->throttlePerMinute();

        $batch = WhatsAppCampaignRecipient::query()

            ->where('campaign_id', $campaign->id)

            ->where('status', 'pending')

            ->orderBy('id')

            ->limit($limit)

            ->get();

        if ($batch->isEmpty()) {

            $campaign->status = 'completed';

            $campaign->save();

            return;

        }

        foreach ($batch as $recipient) {

            $body = $sender->bodyForRecipient($campaign, $recipient);

            $order = $recipient->user_id !== null

                ? Order::query()->where('user_id', $recipient->user_id)->latest()->first()

                : null;

            $ok = $notifier->sendArbitrary(

                recipient: $recipient->phone,

                body: $body,

                templateKey: $campaign->template?->key ?? 'promotional_default',

                audience: 'customer',

                order: $order,

                campaignId: $campaign->id,

            );

            $recipient->status = $ok ? 'sent' : 'failed';

            $recipient->error = $ok ? null : 'Send failed';

            $recipient->sent_at = $ok ? now() : null;

            $recipient->save();

            if ($ok) {

                $campaign->increment('sent_count');

            } else {

                $campaign->increment('failed_count');

            }

        }

        $remaining = WhatsAppCampaignRecipient::query()

            ->where('campaign_id', $campaign->id)

            ->where('status', 'pending')

            ->exists();

        if ($remaining) {

            self::dispatch($campaign->id)->delay(now()->addMinute());

            return;

        }

        $campaign->refresh();

        $campaign->status = 'completed';

        $campaign->save();

    }

}
