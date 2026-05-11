<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use Inertia\Inertia;
use Inertia\Response;

class CourierAdminController extends Controller
{
    public function index(): Response
    {
        $couriers = Courier::query()->latest()->paginate(20);

        return Inertia::render('Admin/Couriers/Index', [
            'couriers' => $couriers,
        ]);
    }
}
