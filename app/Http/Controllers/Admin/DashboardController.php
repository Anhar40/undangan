<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\AdminDashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(AdminDashboardService $service): View
    {
        return view('dashboard.admin', $service->summary());
    }
}
