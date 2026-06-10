<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Notifications\WhatsApp\TemplateRepository;
use App\Domain\Notifications\WhatsApp\WhatsAppClient;
use App\Domain\Notifications\WhatsApp\WhatsAppTemplateSyncService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertWhatsAppTemplateRequest;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppTemplateAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $templates = WhatsAppTemplate::query()
            ->when($search !== '', function ($q) use ($search): void {
                $like = '%'.$search.'%';
                $q->where(function ($qq) use ($like): void {
                    $qq->where('key', 'like', $like)
                        ->orWhere('label', 'like', $like)
                        ->orWhere('body', 'like', $like);
                });
            })
            ->orderByDesc('is_active')
            ->orderBy('audience')
            ->orderBy('key')
            ->paginate(25)
            ->withQueryString();

        $templates->through(fn (WhatsAppTemplate $t): array => $this->serialize($t));

        return Inertia::render('Admin/WhatsApp/Templates/Index', [
            'templates' => $templates,
            'filters' => ['search' => $search],
            'cloud_enabled' => (bool) config('whatsapp.cloud.enabled', false),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/WhatsApp/Templates/Edit', [
            'template' => $this->emptyTemplatePayload(),
            'audiences' => $this->audiences(),
            'categories' => $this->categories(),
            'cloud_enabled' => (bool) config('whatsapp.cloud.enabled', false),
        ]);
    }

    public function store(UpsertWhatsAppTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_system'] = false;
        WhatsAppTemplate::query()->create($data);

        return redirect()
            ->route('admin.whatsapp-templates.index')
            ->with('status', 'WhatsApp template created.');
    }

    public function edit(WhatsAppTemplate $whatsappTemplate): Response
    {
        return Inertia::render('Admin/WhatsApp/Templates/Edit', [
            'template' => $this->serialize($whatsappTemplate),
            'audiences' => $this->audiences(),
            'categories' => $this->categories(),
            'cloud_enabled' => (bool) config('whatsapp.cloud.enabled', false),
        ]);
    }

    public function update(UpsertWhatsAppTemplateRequest $request, WhatsAppTemplate $whatsappTemplate): RedirectResponse
    {
        $data = $request->validated();
        if ($whatsappTemplate->is_system) {
            unset($data['key']);
        }
        $whatsappTemplate->fill($data)->save();

        return redirect()
            ->route('admin.whatsapp-templates.index')
            ->with('status', 'WhatsApp template updated.');
    }

    public function destroy(WhatsAppTemplate $whatsappTemplate): RedirectResponse
    {
        if ($whatsappTemplate->is_system) {
            return back()->with('error', 'System templates cannot be deleted. Deactivate them instead.');
        }

        $whatsappTemplate->delete();

        return redirect()
            ->route('admin.whatsapp-templates.index')
            ->with('status', 'Template deleted.');
    }

    public function syncToMeta(Request $request, WhatsAppTemplate $whatsappTemplate, WhatsAppTemplateSyncService $sync): RedirectResponse
    {
        $force = $request->boolean('force');

        try {
            $result = $sync->sync($whatsappTemplate, $force);
        } catch (\Throwable $e) {
            return back()->with('error', 'Meta sync failed: '.$e->getMessage());
        }

        if ($result['ok']) {
            return back()->with('status', $result['message']);
        }

        if ($result['action'] === 'skipped') {
            return back()->with('error', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function syncAllToMeta(Request $request, WhatsAppTemplateSyncService $sync): RedirectResponse
    {
        $force = $request->boolean('force');

        try {
            $summary = $sync->syncAll(onlyActive: true, force: $force);
        } catch (\Throwable $e) {
            return back()->with('error', 'Meta sync failed: '.$e->getMessage());
        }

        $message = "Meta sync finished — created: {$summary['created']}, updated: {$summary['updated']}, unchanged: {$summary['unchanged']}, skipped: {$summary['skipped']}, failed: {$summary['failed']}.";

        if ($summary['failed'] > 0) {
            return back()->with('error', $message.' '.implode(' ', $summary['errors']));
        }

        return back()->with('status', $message);
    }

    public function sendTest(Request $request, WhatsAppTemplate $whatsappTemplate, WhatsAppClient $client, TemplateRepository $templates): RedirectResponse
    {
        $data = $request->validate([
            'recipient' => ['required', 'string', 'min:7', 'max:32'],
        ]);

        $body = $templates->renderPlaceholders($whatsappTemplate->body, $this->sampleOrder());
        $to = $this->normalizePakE164($data['recipient']);

        if ($to === null) {
            return back()->with('error', 'Recipient phone number is invalid.');
        }

        $cloudEnabled = (bool) config('whatsapp.cloud.enabled', false);

        $payload = $cloudEnabled
            ? [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => ltrim($to, '+'),
                'type' => 'text',
                'text' => ['preview_url' => false, 'body' => $body],
            ]
            : [
                'to' => $to,
                'body' => $body,
                'template_key' => $whatsappTemplate->key,
                'from' => config('whatsapp.bridge.from_number'),
            ];

        try {
            $response = $cloudEnabled
                ? $client->sendCloudMessage($payload)
                : $client->sendBridgeMessage($payload);

            NotificationLog::query()->create([
                'channel' => 'whatsapp',
                'recipient' => $to,
                'template_key' => $whatsappTemplate->key,
                'payload' => [
                    'audience' => 'test',
                    'request' => $payload,
                    'response' => $response,
                ],
                'status' => 'sent',
            ]);

            return back()->with('status', 'Test WhatsApp message sent.');
        } catch (\Throwable $e) {
            NotificationLog::query()->create([
                'channel' => 'whatsapp',
                'recipient' => $to,
                'template_key' => $whatsappTemplate->key,
                'payload' => [
                    'audience' => 'test',
                    'request' => $payload,
                ],
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return back()->with('error', 'Test failed: '.$e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(WhatsAppTemplate $t): array
    {
        return [
            'id' => $t->id,
            'key' => $t->key,
            'label' => $t->label,
            'audience' => $t->audience,
            'category' => $t->category,
            'body' => $t->body,
            'header_text' => $t->header_text,
            'footer_text' => $t->footer_text,
            'url_buttons' => is_array($t->url_buttons) ? $t->url_buttons : [],
            'cloud_template_name' => $t->cloud_template_name,
            'cloud_template_language' => $t->cloud_template_language,
            'has_buttons' => (bool) $t->has_buttons,
            'button_payloads' => is_array($t->button_payloads) ? $t->button_payloads : [],
            'is_active' => (bool) $t->is_active,
            'is_system' => (bool) $t->is_system,
            'description' => $t->description,
            'meta_sync_status' => $t->meta_sync_status,
            'meta_sync_error' => $t->meta_sync_error,
            'meta_last_synced_at' => $t->meta_last_synced_at?->format('M j, Y H:i'),
            'updated_at' => $t->updated_at?->format('M j, Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyTemplatePayload(): array
    {
        return [
            'id' => null,
            'key' => '',
            'label' => '',
            'audience' => 'customer',
            'category' => 'transactional',
            'body' => '',
            'header_text' => '',
            'footer_text' => '',
            'url_buttons' => [],
            'cloud_template_name' => '',
            'cloud_template_language' => 'en_US',
            'has_buttons' => false,
            'button_payloads' => [],
            'is_active' => true,
            'is_system' => false,
            'description' => '',
        ];
    }

    /**
     * @return list<array{value:string, label:string}>
     */
    private function audiences(): array
    {
        return [
            ['value' => 'customer', 'label' => 'Customer'],
            ['value' => 'admin', 'label' => 'Admin'],
            ['value' => 'rider', 'label' => 'Courier rider'],
        ];
    }

    /**
     * @return list<array{value:string, label:string}>
     */
    private function categories(): array
    {
        return [
            ['value' => 'transactional', 'label' => 'Transactional (orders, shipping)'],
            ['value' => 'utility', 'label' => 'Utility (pickup notices, alerts)'],
            ['value' => 'marketing', 'label' => 'Marketing / promotional'],
        ];
    }

    private function normalizePakE164(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '92')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+92'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '+92'.$digits;
        }

        return '+'.$digits;
    }

    /**
     * In-memory fake Order to preview placeholder rendering for test sends.
     */
    private function sampleOrder(): Order
    {
        $order = new Order;
        $order->forceFill([
            'id' => 0,
            'order_number' => 'TEST-0001',
            'grand_total' => 4500,
            'status' => 'processing',
            'payment_gateway' => 'cod',
            'shipping_address_snapshot' => [
                'full_name' => 'Test Customer',
                'phone' => '+923001234567',
                'city' => 'Karachi',
            ],
        ]);

        return $order;
    }
}
