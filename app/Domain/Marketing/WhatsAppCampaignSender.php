<?php

namespace App\Domain\Marketing;

use App\Domain\Notifications\WhatsApp\TemplateRepository;
use App\Enums\OrderStatus;
use App\Jobs\SendCampaignBatchJob;
use App\Models\Order;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\DB;

class WhatsAppCampaignSender
{
    public function __construct(

        private readonly WhatsAppCampaignSegmentBuilder $segments,

    ) {}

    /**
     * Build recipient rows from segment filters and move campaign to sending.
     */
    public function prepareAndQueue(WhatsAppCampaign $campaign): void
    {

        $segment = is_array($campaign->segment) ? $campaign->segment : [];

        DB::transaction(function () use ($campaign, $segment): void {

            $campaign->recipients()->delete();

            foreach ($this->segments->resolveRecipients($segment) as $row) {

                $status = 'pending';

                if ($row['user_id'] !== null) {

                    $user = User::query()->find($row['user_id']);

                    if ($user?->whatsapp_opted_out) {

                        $status = 'opted_out';

                    }

                }

                WhatsAppCampaignRecipient::query()->create([

                    'campaign_id' => $campaign->id,

                    'user_id' => $row['user_id'],

                    'phone' => $row['phone'],

                    'name' => $row['name'],

                    'status' => $status,

                ]);

            }

            $campaign->status = 'sending';

            $campaign->save();

        });

        SendCampaignBatchJob::dispatch($campaign->id);

    }

    public function bodyForRecipient(WhatsAppCampaign $campaign, WhatsAppCampaignRecipient $recipient): string
    {

        $template = $campaign->template_id

            ? WhatsAppTemplate::query()->find($campaign->template_id)

            : WhatsAppTemplate::findActiveByKey('promotional_default');

        $body = $template?->body ?? 'Hi {name}, check out our latest collection!';

        $order = null;

        if ($recipient->user_id !== null) {

            $order = Order::query()

                ->where('user_id', $recipient->user_id)

                ->whereNot('status', OrderStatus::Cancelled)

                ->latest()

                ->first();

        }

        $name = $recipient->name ?? $order?->shipping_address_snapshot['full_name'] ?? 'there';

        if ($order !== null) {

            return app(TemplateRepository::class)

                ->renderPlaceholders($body, $order);

        }

        return strtr($body, ['{name}' => (string) $name]);

    }

    public function throttlePerMinute(): int
    {

        return max(1, (int) WhatsAppSetting::current()->promotional_throttle_per_minute);

    }

}
