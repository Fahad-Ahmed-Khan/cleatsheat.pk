<?php

namespace App\Http\Controllers\Web\Store\Account;

use App\Domain\Orders\CustomerOrderQueryService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Store\Account\Concerns\BuildsAccountSeo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use BuildsAccountSeo;

    public function __construct(
        private readonly CustomerOrderQueryService $orders,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $recent = $this->orders->paginateForUser($user, 1);
        $recentOrder = $recent->first();

        return Inertia::render('Store/Account/Dashboard', [
            'seo' => $this->accountSeo('My account', '/account'),
            'recentOrder' => $recentOrder
                ? $this->orders->toOrderListPayload($recentOrder)
                : null,
            'ordersCount' => $this->orders->countForUser($user),
        ]);
    }
}
