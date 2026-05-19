<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Finance\CourierSettlementService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourierSettlementAdminController extends Controller
{
    public function __construct(
        private readonly CourierSettlementService $service,
    ) {}

    public function index(Request $request): Response
    {
        $couriers = $this->service->summaryPerCourier();

        $totals = [
            'outstanding_count' => (int) $couriers->sum('outstanding_count'),
            'outstanding_amount' => (float) $couriers->sum('outstanding_amount'),
            'settled_count' => (int) $couriers->sum('settled_count'),
            'settled_amount' => (float) $couriers->sum('settled_amount'),
        ];

        $focusCourierId = $request->integer('courier');
        $outstanding = $focusCourierId > 0
            ? $this->service->outstandingShipmentsForCourier($focusCourierId)
            : collect();

        return Inertia::render('Admin/Finance/CourierSettlements', [
            'couriers' => $couriers->values()->all(),
            'totals' => $totals,
            'focus_courier_id' => $focusCourierId > 0 ? $focusCourierId : null,
            'outstanding' => $outstanding->values()->all(),
        ]);
    }
}
