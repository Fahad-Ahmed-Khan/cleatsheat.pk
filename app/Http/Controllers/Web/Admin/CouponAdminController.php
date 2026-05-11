<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CouponAdminController extends Controller
{
    public function index(): Response
    {
        $coupons = Coupon::query()->latest()->paginate(20);

        return Inertia::render('Admin/Coupons/Index', [
            'coupons' => $coupons,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Coupons/Create');
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if (! array_key_exists('is_active', $data)) {
            $data['is_active'] = true;
        }

        Coupon::query()->create($data);

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon created');
    }
}
