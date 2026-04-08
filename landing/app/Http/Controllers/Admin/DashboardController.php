<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private AdminDashboardService $dashboard,
    ) {}

    public function index(): View
    {
        return view('admin.dashboard', $this->dashboard->build());
    }
}
