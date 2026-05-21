<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Notifications\WhatsApp\ManualMessageService;
use App\Domain\Notifications\WhatsApp\WhatsAppNotifier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertCourierRiderRequest;
use App\Models\Courier;
use App\Models\CourierRider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CourierRiderAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $courierId = (int) $request->query('courier_id', 0) ?: null;
        $search = trim((string) $request->query('search', ''));

        $riders = CourierRider::query()
            ->with('courier:id,name,code')
            ->when($courierId, fn ($q, $v) => $q->where('courier_id', $v))
            ->when($search !== '', function ($q) use ($search): void {
                $like = '%'.$search.'%';
                $q->where(function ($qq) use ($like): void {
                    $qq->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->orderByDesc('is_primary')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $riders->through(fn (CourierRider $r): array => $this->serialize($r));

        return Inertia::render('Admin/Couriers/Riders/Index', [
            'riders' => $riders,
            'couriers' => Courier::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'filters' => ['search' => $search, 'courier_id' => $courierId],
        ]);
    }

    public function create(): Response
    {
        $manual = app(ManualMessageService::class);

        return Inertia::render('Admin/Couriers/Riders/Edit', [
            'rider' => $this->emptyRiderPayload(),
            'couriers' => Courier::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'whatsapp_templates' => $manual->riderTemplateOptions(),
            'whatsapp_send_route' => null,
        ]);
    }

    public function store(UpsertCourierRiderRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $request->validated();
            if ($data['is_primary'] ?? false) {
                CourierRider::query()
                    ->where('courier_id', $data['courier_id'])
                    ->update(['is_primary' => false]);
            }
            CourierRider::query()->create($data);
        });

        return redirect()
            ->route('admin.riders.index')
            ->with('status', 'Rider added.');
    }

    public function edit(CourierRider $rider): Response
    {
        $manual = app(ManualMessageService::class);

        return Inertia::render('Admin/Couriers/Riders/Edit', [
            'rider' => $this->serialize($rider),
            'couriers' => Courier::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'whatsapp_templates' => $manual->riderTemplateOptions(),
            'whatsapp_send_route' => $rider->id ? route('admin.riders.whatsapp.send', $rider) : null,
        ]);
    }

    public function update(UpsertCourierRiderRequest $request, CourierRider $rider): RedirectResponse
    {
        DB::transaction(function () use ($request, $rider): void {
            $data = $request->validated();
            if (($data['is_primary'] ?? false) && ! $rider->is_primary) {
                CourierRider::query()
                    ->where('courier_id', $data['courier_id'])
                    ->where('id', '!=', $rider->id)
                    ->update(['is_primary' => false]);
            }
            $rider->fill($data)->save();
        });

        return redirect()
            ->route('admin.riders.index')
            ->with('status', 'Rider updated.');
    }

    public function destroy(CourierRider $rider): RedirectResponse
    {
        $rider->delete();

        return redirect()
            ->route('admin.riders.index')
            ->with('status', 'Rider removed.');
    }

    public function sendTest(Request $request, CourierRider $rider, WhatsAppNotifier $notifier): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:1000'],
        ]);

        $body = $data['body'] ?: ("Salaam {$rider->name}, this is a test message from ".(string) (Courier::query()->find($rider->courier_id)->name ?? 'Tryino').' admin panel.');

        $ok = $notifier->sendArbitrary(
            recipient: $rider->phone,
            body: $body,
            templateKey: 'rider_test',
            audience: 'rider',
        );

        return back()->with($ok ? 'status' : 'error', $ok ? 'Test message queued.' : 'Test failed — see notification log.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CourierRider $r): array
    {
        return [
            'id' => $r->id,
            'courier_id' => $r->courier_id,
            'courier' => $r->relationLoaded('courier') && $r->courier
                ? ['id' => $r->courier->id, 'name' => $r->courier->name, 'code' => $r->courier->code]
                : null,
            'name' => $r->name,
            'phone' => $r->phone,
            'alt_phone' => $r->alt_phone,
            'is_active' => (bool) $r->is_active,
            'is_primary' => (bool) $r->is_primary,
            'notes' => $r->notes,
            'updated_at' => $r->updated_at?->format('M j, Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyRiderPayload(): array
    {
        return [
            'id' => null,
            'courier_id' => null,
            'name' => '',
            'phone' => '',
            'alt_phone' => '',
            'is_active' => true,
            'is_primary' => true,
            'notes' => '',
        ];
    }
}
