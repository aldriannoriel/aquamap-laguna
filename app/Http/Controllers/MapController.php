<?php

namespace App\Http\Controllers;

use App\Models\WaterBody;
use Illuminate\Contracts\View\View;

class MapController extends Controller
{
    public function index(): View
    {
        $fields = ['id', 'name', 'municipality', 'water_type', 'latitude', 'longitude', 'description', 'source_url'];

        $lakes = WaterBody::query()
            ->where('municipality', 'San Pablo City')
            ->whereIn('name', ['Sampaloc Lake', 'Pandin Lake', 'Yambo Lake'])
            ->orderBy('name')
            ->get($fields)
            ->map(function (WaterBody $lake) use ($fields): array {
                $data = $lake->only($fields);
                $data['description'] = filled($data['description']) ? trim($data['description']) : null;
                $source = $data['source_url'];
                $data['source_url'] = is_string($source)
                    && filter_var($source, FILTER_VALIDATE_URL)
                    && in_array(strtolower((string) parse_url($source, PHP_URL_SCHEME)), ['http', 'https'], true)
                        ? $source : null;

                return $data;
            })
            ->all();

        return view('map.index', ['lakes' => $lakes]);
    }
}
