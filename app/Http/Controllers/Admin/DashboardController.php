<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PollutionReport;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $grouped = PollutionReport::query()->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $counts = ['total' => (int) $grouped->sum()];
        foreach (PollutionReport::STATUSES as $status => $label) {
            $counts[$status] = (int) ($grouped[$status] ?? 0);
        }

        return view('admin.dashboard', ['counts' => $counts, 'actionCount' => PollutionReport::actionRequired()->count()]);
    }
}
