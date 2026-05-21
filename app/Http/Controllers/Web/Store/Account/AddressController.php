<?php

namespace App\Http\Controllers\Web\Store\Account;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Store\Account\Concerns\BuildsAccountSeo;
use App\Http\Requests\Account\AddressRequest;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    use BuildsAccountSeo;

    public function index(Request $request): Response
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Address $a) => $this->serializeAddress($a))
            ->values()
            ->all();

        return Inertia::render('Store/Account/Addresses/Index', [
            'seo' => $this->accountSeo('Saved addresses', '/account/addresses'),
            'addresses' => $addresses,
            'editing' => $this->editingPayload($request),
        ]);
    }

    public function store(AddressRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (! empty($data['is_default'])) {
            $user->addresses()->update(['is_default' => false]);
        } elseif (! $user->addresses()->exists()) {
            $data['is_default'] = true;
        }

        $user->addresses()->create($data);

        return Redirect::route('store.account.addresses')->with('status', 'address-created');
    }

    public function update(AddressRequest $request, Address $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);

        $data = $request->validated();

        if (! empty($data['is_default'])) {
            $request->user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($data);

        return Redirect::route('store.account.addresses')->with('status', 'address-updated');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $next = $request->user()->addresses()->orderByDesc('id')->first();
            $next?->update(['is_default' => true]);
        }

        return Redirect::route('store.account.addresses')->with('status', 'address-deleted');
    }

    private function authorizeAddress(Request $request, Address $address): void
    {
        abort_if($address->user_id !== $request->user()->id, 404);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function editingPayload(Request $request): ?array
    {
        $editId = $request->query('edit');
        if ($editId === null || $editId === '') {
            return null;
        }

        $address = $request->user()->addresses()->whereKey($editId)->first();
        if ($address === null) {
            return null;
        }

        return $this->serializeAddress($address);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAddress(Address $address): array
    {
        return [
            'id' => $address->id,
            'full_name' => $address->full_name,
            'phone' => $address->phone,
            'line1' => $address->line1,
            'city' => $address->city,
            'area' => $address->area,
            'postal_code' => $address->postal_code,
            'is_default' => (bool) $address->is_default,
        ];
    }
}
