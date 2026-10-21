<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\PollutionReport;
use App\Support\MunicipalityRouting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IncidentMunicipalityController extends Controller
{
    public function __invoke(Request $request, PollutionReport $pollutionReport): RedirectResponse
    {
        $data = $request->validate([
            'agency_id' => ['required', 'integer', 'exists:agencies,id'],
            'routing_reason' => ['required', 'string', 'max:2000'],
            'point_key' => ['required', 'string', 'size:64'],
            'expected_status' => ['required', Rule::in(['pending', 'verified'])],
        ], ['routing_reason.required' => 'Explain the incident municipality selection or override.']);
        MunicipalityRouting::select($pollutionReport, Agency::findOrFail($data['agency_id']), $request->user(),
            $data['routing_reason'], $data['point_key'], $data['expected_status']);

        return to_route('admin.reports.show', $pollutionReport)->with('routing_saved', 'Incident municipality saved in the audit history. No email was sent.');
    }
}
