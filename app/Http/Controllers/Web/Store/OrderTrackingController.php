<?php

namespace App\Http\Controllers\Web\Store;

use App\Domain\Orders\CustomerOrderQueryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderTrackingController extends Controller
{
    public function __construct(
        private readonly CustomerOrderQueryService $orders,
    ) {}

    public function show(Request $request): Response
    {
        return Inertia::render('Store/OrderTracking', [
            'seo' => ['title' => 'Track order — '.config('app.name')],
            'result' => null,
        ]);
    }

    public function lookup(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:64'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $order = $this->orders->findForPublicTracking($data['order_number'], $data['email']);

        if ($order === null) {
            return back()->withErrors([
                'order_number' => 'No order matches that reference and email.',
            ])->withInput();
        }

        return Inertia::render('Store/OrderTracking', [
            'seo' => ['title' => 'Track order — '.config('app.name')],
            'result' => $this->orders->toTrackingPayload($order),
        ]);
    }
}
