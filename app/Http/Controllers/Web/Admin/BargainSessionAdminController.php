<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\BargainSessionState;
use App\Http\Controllers\Controller;
use App\Models\BargainSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BargainSessionAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $stateFilter = (string) $request->query('state', '');
        $search = trim((string) $request->query('search', ''));

        $query = BargainSession::query()
            ->with([
                'variant.product',
                'variant.color',
                'user',
            ])
            ->withCount('messages');

        if ($stateFilter !== '' && $this->isValidStateFilter($stateFilter)) {
            $query->where('state', $stateFilter);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($w) use ($like): void {
                $w->where('customer_phone', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhereHas('variant', function ($v) use ($like): void {
                        $v->where('sku', 'like', $like);
                    })
                    ->orWhereHas('variant.product', function ($p) use ($like): void {
                        $p->where('name', 'like', $like);
                    })
                    ->orWhereHas('user', function ($u) use ($like): void {
                        $u->where('email', 'like', $like);
                    });
            });
        }

        $sessions = $query
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (BargainSession $s): array => $this->serializeSessionRow($s));

        return Inertia::render('Admin/Bargaining/Index', [
            'sessions' => $sessions,
            'filters' => [
                'state' => $stateFilter,
                'search' => $search,
            ],
            'state_options' => collect(BargainSessionState::cases())->map(fn (BargainSessionState $e) => [
                'value' => $e->value,
                'label' => $this->stateAdminLabel($e),
            ])->values()->all(),
        ]);
    }

    public function show(BargainSession $bargain_session): Response
    {
        $bargain_session->load([
            'variant.product',
            'variant.color',
            'user',
            'messages',
        ]);
        $bargain_session->loadCount('messages');

        return Inertia::render('Admin/Bargaining/Show', [
            'session' => $this->serializeSessionDetail($bargain_session),
            'messages' => $bargain_session->messages->map(fn ($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'body' => $m->body,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    private function isValidStateFilter(string $value): bool
    {
        foreach (BargainSessionState::cases() as $case) {
            if ($case->value === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSessionRow(BargainSession $s): array
    {
        $state = $s->state;

        return [
            'id' => $s->id,
            'state' => $state->value,
            'outcome' => $this->outcomeLabel($state),
            'outcome_kind' => $this->outcomeKind($state),
            'product_name' => $s->variant?->product?->name,
            'sku' => $s->variant?->sku,
            'customer_phone' => $s->customer_phone,
            'customer_name' => $s->customer_name,
            'user_email' => $s->user?->email,
            'list_price' => (string) $s->list_price,
            'current_offer' => $s->current_offer !== null ? (string) $s->current_offer : null,
            'accepted_price' => $s->accepted_price !== null ? (string) $s->accepted_price : null,
            'expires_at' => $s->expires_at?->toIso8601String(),
            'updated_at' => $s->updated_at?->toIso8601String(),
            'messages_count' => (int) $s->messages_count,
            'lock_consumed_at' => $s->lock_consumed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSessionDetail(BargainSession $s): array
    {
        $base = $this->serializeSessionRow($s);
        $base['variant_label'] = $s->variant?->color?->name;

        return $base;
    }

    private function outcomeLabel(BargainSessionState $state): string
    {
        return match ($state) {
            BargainSessionState::Accepted => 'Accepted',
            BargainSessionState::Declined => 'Walk away',
            BargainSessionState::Consumed => 'Checked out',
            BargainSessionState::Open, BargainSessionState::Countered => 'Incomplete',
            BargainSessionState::Expired => 'Expired',
        };
    }

    /** For badge styling in the UI. */
    private function outcomeKind(BargainSessionState $state): string
    {
        return match ($state) {
            BargainSessionState::Accepted, BargainSessionState::Consumed => 'success',
            BargainSessionState::Declined, BargainSessionState::Expired => 'danger',
            BargainSessionState::Open, BargainSessionState::Countered => 'warning',
        };
    }

    private function stateAdminLabel(BargainSessionState $state): string
    {
        return match ($state) {
            BargainSessionState::Open => 'Open',
            BargainSessionState::Countered => 'Countered',
            BargainSessionState::Accepted => 'Accepted',
            BargainSessionState::Declined => 'Declined',
            BargainSessionState::Expired => 'Expired',
            BargainSessionState::Consumed => 'Consumed (checkout)',
        };
    }
}
