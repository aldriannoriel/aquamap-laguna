<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePollutionReportRequest;
use App\Models\PollutionReport;
use App\Models\WaterBody;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PollutionReportController extends Controller
{
    public function create(): Response
    {
        $lakes = WaterBody::query()->orderBy('name')->get(['id', 'name', 'latitude', 'longitude']);

        return response()->view('reports.create', [
            'lakes' => $lakes,
            'types' => PollutionReport::TYPES,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(StorePollutionReportRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $photoPath = null;

        try {
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('', ['disk' => 'pollution-reports', 'visibility' => 'private']);
            }

            $report = new PollutionReport;
            $report->water_body_id = $data['water_body_id'];
            $report->pollution_type = $data['pollution_type'];
            $report->description = $data['description'];
            $report->reporter_name = $data['reporter_name'] ?? null;
            $report->reporter_email = $data['reporter_email'] ?? null;
            $report->latitude = $data['latitude'] ?? null;
            $report->longitude = $data['longitude'] ?? null;
            $report->photo_path = $photoPath;
            $report->status = 'pending';
            $report->save();
        } catch (Throwable $exception) {
            if ($photoPath !== null) {
                try {
                    Storage::disk('pollution-reports')->delete($photoPath);
                } catch (Throwable) {
                    Log::error('Private pollution photo cleanup failed; check storage availability.');
                }
            }

            // Do not log contact details, uploaded contents or SQL bindings.
            Log::error('Pollution report submission failed.', ['exception_type' => $exception::class]);

            return redirect()->route('reports.create')->withInput($request->safeOldInput())
                ->withErrors(['submission' => 'We could not save your report. Please try again. If you selected a photo, choose it again.'])
                ->header('Cache-Control', 'no-store, private');
        }

        return redirect()->route('reports.create')->with('report_received', true)
            ->header('Cache-Control', 'no-store, private');
    }
}
