<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportFilterRequest;
use App\Models\PollutionReport;
use App\Models\WaterBody;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(ReportFilterRequest $request): View
    {
        $filters = $request->validated();
        $query = PollutionReport::query()->with('waterBody:id,name');
        foreach (['lake' => 'water_body_id', 'type' => 'pollution_type', 'status' => 'status'] as $filter => $column) {
            if (isset($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if (isset($filters['q'])) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('description', 'like', '%'.$filters['q'].'%')
                    ->orWhereHas('waterBody', fn (Builder $lakes) => $lakes->where('name', 'like', '%'.$filters['q'].'%'));
            });
        }
        if (isset($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return view('admin.reports.index', [
            'reports' => $query->latest('id')->paginate(15)->appends($filters),
            'filters' => $filters, 'lakes' => WaterBody::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(PollutionReport $pollutionReport): View
    {
        $pollutionReport->load(['waterBody:id,name,municipality', 'reviews.administrator:id,name']);

        return view('admin.reports.show', ['report' => $pollutionReport]);
    }

    public function photo(PollutionReport $pollutionReport): StreamedResponse
    {
        $path = $pollutionReport->photo_path;
        abort_unless(is_string($path) && preg_match('/\A[A-Za-z0-9_-]+\.(jpe?g|png|webp)\z/i', $path), 404);
        $disk = Storage::disk('pollution-reports');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        abort_unless(isset($extensions[$mime]), 404);

        return $disk->response($path, 'private-evidence.'.$extensions[$mime], [
            'Content-Type' => $mime, 'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
