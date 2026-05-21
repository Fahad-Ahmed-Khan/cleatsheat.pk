<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Marketing\WhatsAppCampaignSegmentBuilder;
use App\Domain\Marketing\WhatsAppCampaignSender;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppCampaignAdminController extends Controller
{
    public function index(Request $request): Response
    {

        $campaigns = WhatsAppCampaign::query()

            ->with('template:id,key,label')

            ->orderByDesc('id')

            ->paginate(20)

            ->withQueryString();

        $campaigns->through(fn (WhatsAppCampaign $c): array => [

            'id' => $c->id,

            'name' => $c->name,

            'status' => $c->status,

            'template_label' => $c->template?->label ?? $c->template?->key ?? '—',

            'sent_count' => $c->sent_count,

            'failed_count' => $c->failed_count,

            'scheduled_for' => $c->scheduled_for?->toIso8601String(),

            'created_at' => $c->created_at?->format('M j, Y H:i'),

        ]);

        return Inertia::render('Admin/WhatsApp/Campaigns/Index', [

            'campaigns' => $campaigns,

        ]);

    }

    public function create(): Response
    {

        return $this->formResponse(new WhatsAppCampaign(['status' => 'draft', 'segment' => []]));

    }

    public function store(Request $request, WhatsAppCampaignSegmentBuilder $segments): RedirectResponse
    {

        $data = $this->validated($request);

        $campaign = WhatsAppCampaign::query()->create([

            ...$data,

            'created_by' => Auth::id(),

            'status' => 'draft',

        ]);

        return redirect()

            ->route('admin.whatsapp-campaigns.show', $campaign)

            ->with('status', 'Campaign draft saved. Audience: '.$segments->countPreview($data['segment'] ?? []).' recipients.');

    }

    public function show(WhatsAppCampaign $whatsappCampaign): Response
    {

        $whatsappCampaign->load('template:id,key,label');

        $stats = [

            'pending' => $whatsappCampaign->recipients()->where('status', 'pending')->count(),

            'sent' => $whatsappCampaign->recipients()->where('status', 'sent')->count(),

            'failed' => $whatsappCampaign->recipients()->where('status', 'failed')->count(),

            'opted_out' => $whatsappCampaign->recipients()->where('status', 'opted_out')->count(),

            'skipped' => $whatsappCampaign->recipients()->where('status', 'skipped')->count(),

        ];

        $recent = $whatsappCampaign->recipients()

            ->orderByDesc('id')

            ->limit(50)

            ->get(['id', 'phone', 'name', 'status', 'error', 'sent_at']);

        return Inertia::render('Admin/WhatsApp/Campaigns/Show', [

            'campaign' => [

                'id' => $whatsappCampaign->id,

                'name' => $whatsappCampaign->name,

                'status' => $whatsappCampaign->status,

                'segment' => $whatsappCampaign->segment ?? [],

                'template' => $whatsappCampaign->template ? [

                    'id' => $whatsappCampaign->template->id,

                    'key' => $whatsappCampaign->template->key,

                    'label' => $whatsappCampaign->template->label,

                ] : null,

                'sent_count' => $whatsappCampaign->sent_count,

                'failed_count' => $whatsappCampaign->failed_count,

                'scheduled_for' => $whatsappCampaign->scheduled_for?->toIso8601String(),

            ],

            'stats' => $stats,

            'recent_recipients' => $recent,

        ]);

    }

    public function edit(WhatsAppCampaign $whatsappCampaign): Response
    {

        return $this->formResponse($whatsappCampaign);

    }

    public function update(Request $request, WhatsAppCampaign $whatsappCampaign, WhatsAppCampaignSegmentBuilder $segments): RedirectResponse
    {

        if (! in_array($whatsappCampaign->status, ['draft', 'scheduled'], true)) {

            return back()->with('error', 'Only draft or scheduled campaigns can be edited.');

        }

        $data = $this->validated($request);

        $whatsappCampaign->update($data);

        return redirect()

            ->route('admin.whatsapp-campaigns.show', $whatsappCampaign)

            ->with('status', 'Campaign updated. Preview audience: '.$segments->countPreview($data['segment'] ?? []));

    }

    public function destroy(WhatsAppCampaign $whatsappCampaign): RedirectResponse
    {

        if ($whatsappCampaign->status === 'sending') {

            return back()->with('error', 'Cannot delete a campaign while it is sending.');

        }

        $whatsappCampaign->delete();

        return redirect()

            ->route('admin.whatsapp-campaigns.index')

            ->with('status', 'Campaign deleted.');

    }

    public function sendNow(WhatsAppCampaign $whatsappCampaign, WhatsAppCampaignSender $sender): RedirectResponse
    {

        if (! in_array($whatsappCampaign->status, ['draft', 'scheduled'], true)) {

            return back()->with('error', 'Campaign cannot be sent in its current state.');

        }

        $sender->prepareAndQueue($whatsappCampaign);

        return back()->with('status', 'Campaign queued for sending.');

    }

    public function cancel(WhatsAppCampaign $whatsappCampaign): RedirectResponse
    {

        if (! in_array($whatsappCampaign->status, ['draft', 'scheduled', 'sending'], true)) {

            return back()->with('error', 'Campaign cannot be cancelled.');

        }

        $whatsappCampaign->status = 'cancelled';

        $whatsappCampaign->save();

        return back()->with('status', 'Campaign cancelled.');

    }

    public function previewCount(Request $request, WhatsAppCampaignSegmentBuilder $segments): JsonResponse
    {

        $segment = $request->validate([

            'segment' => ['nullable', 'array'],

        ])['segment'] ?? [];

        return response()->json(['count' => $segments->countPreview($segment)]);

    }

    private function formResponse(WhatsAppCampaign $campaign): Response
    {

        return Inertia::render('Admin/WhatsApp/Campaigns/Edit', [

            'campaign' => [

                'id' => $campaign->id,

                'name' => $campaign->name ?? '',

                'template_id' => $campaign->template_id,

                'segment' => $campaign->segment ?? [

                    'opt_in_only' => true,

                    'ordered_within_days' => null,

                    'city' => '',

                    'category_id' => null,

                    'phones' => '',

                ],

                'scheduled_for' => $campaign->scheduled_for?->format('Y-m-d\TH:i'),

                'status' => $campaign->status ?? 'draft',

            ],

            'templates' => WhatsAppTemplate::query()

                ->where('is_active', true)

                ->where('category', 'marketing')

                ->orderBy('key')

                ->get(['id', 'key', 'label']),

            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),

        ]);

    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {

        $data = $request->validate([

            'name' => ['required', 'string', 'max:120'],

            'template_id' => ['nullable', 'integer', 'exists:whatsapp_templates,id'],

            'segment' => ['nullable', 'array'],

            'segment.opt_in_only' => ['nullable', 'boolean'],

            'segment.ordered_within_days' => ['nullable', 'integer', 'min:1', 'max:365'],

            'segment.city' => ['nullable', 'string', 'max:80'],

            'segment.category_id' => ['nullable', 'integer', 'exists:categories,id'],

            'segment.phones' => ['nullable', 'string', 'max:8000'],

            'scheduled_for' => ['nullable', 'date'],

        ]);

        $status = 'draft';

        if (! empty($data['scheduled_for'])) {

            $status = 'scheduled';

        }

        return [

            'name' => $data['name'],

            'template_id' => $data['template_id'] ?? null,

            'segment' => $data['segment'] ?? [],

            'scheduled_for' => $data['scheduled_for'] ?? null,

            'status' => $status,

        ];

    }
}
