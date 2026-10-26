<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(Request $request): View
    {
        [$from, $to] = Period::fromRequest($request, 'last_12_months');

        return view('admin.dashboard', [
            'from' => $from,
            'to' => $to,
            'preset' => $request->query('periodo', 'last_12_months'),
            'm' => $this->dashboard->metrics($from, $to),
            'charts' => $this->dashboard->charts($from, $to),
            'work' => $this->dashboard->worklists(),
        ]);
    }
}
