<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PollutionReport;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $counts = ['total' => PollutionReport::query()->count()];
        foreach (PollutionReport::CATEGORIES as $category => $label) {
            $counts[$category] = PollutionReport::query()->category($category)->count();
        }

        return view('admin.dashboard', ['counts' => $counts]);
    }
}
