<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminDashboardMetrics;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', AdminDashboardMetrics::build());
    }
}
