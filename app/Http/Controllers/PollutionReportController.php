<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePollutionReportRequest;
use App\Models\PollutionReport;
use App\Models\WaterBody;
use App\Rules\ReportFormToken;
use App\Support\ExplorerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PollutionReportController extends Controller
{
    public function create(Request $request): Response
    {
        $lakes = WaterBody::query()->supported()
            ->get(['id', 'name', 'category', 'geography'])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (WaterBody $water): object => (object) [
                'id' => $water->id, 'name' => $water->name, 'category' => $water->category,
                ...ExplorerProfile::referencePoint($water->geography) ?? ['latitude' => null, 'longitude' => null],
            ]);

        return response()->view('reports.create', [
            'lakes' => $lakes,
            'types' => PollutionReport::TYPES,
            'categories' => WaterBody::REPORT_CATEGORIES,
            'reportFormToken' => ReportFormToken::issue($request),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(StorePollutionReportRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $photoPath = null;

        try {
            $receiptIds = $request->session()->get('recent_pollution_report_ids', []);
            $receiptIds = is_array($receiptIds) ? array_slice(array_filter($receiptIds, 'is_int'), -10) : [];
            $recent = PollutionReport::query()->whereKey($receiptIds)->where('created_at', '>=', now()->subHour())
                ->orderBy('id')->get(['id', 'water_body_id', 'pollution_type']);
            $possibleDuplicate = $recent->contains(fn (PollutionReport $previous): bool => (int) $previous->water_body_id === (int) $data['water_body_id'] && $previous->pollution_type === $data['pollution_type']);

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
            $report->landmark = $data['landmark'] ?? null;
            $report->observed_on = $data['observed_on'] ?? null;
            $report->location_accuracy_m = $data['location_accuracy_m'] ?? null;
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

        $request->session()->put('recent_pollution_report_ids', $recent->pluck('id')->push($report->id)->take(-10)->all());

        return redirect()->route('reports.create')->with('report_received', true)->with('possible_duplicate', $possibleDuplicate)
            ->header('Cache-Control', 'no-store, private');
    }
}
