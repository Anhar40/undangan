<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\CustomerDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(CustomerDashboardService $service): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user?->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('dashboard.customer', $service->for($user));
    }
}
