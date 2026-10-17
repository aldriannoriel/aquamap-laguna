<?php

namespace App\Http\Controllers;

use App\Models\FishSpecies;
use App\Models\WaterBody;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MapController extends Controller
{
    public function index(): View
    {
        $fields = ['id', 'name', 'municipality', 'water_type', 'latitude', 'longitude', 'description', 'source_url'];

        $records = WaterBody::query()
            ->where('municipality', 'San Pablo City')
            ->whereIn('name', ['Sampaloc Lake', 'Pandin Lake', 'Yambo Lake'])
            ->orderBy('name')
            ->with(['fishSpecies' => fn (BelongsToMany $query): BelongsToMany => $query
                ->select(['fish_species.id', 'common_name', 'scientific_name', 'water_type', 'habitat_requirements', 'fish_species.source_url'])
                ->orderBy('common_name')])
            ->get($fields);

        $lakes = $records
            ->map(function (WaterBody $lake) use ($fields): array {
                $data = $lake->only($fields);
                $data['description'] = filled($data['description']) ? trim($data['description']) : null;
                $data['source_url'] = $this->publicSource($data['source_url']);

                return $data;
            })
            ->all();

        $fishByLake = $records->mapWithKeys(function (WaterBody $lake): array {
            $fish = $lake->fishSpecies->map(function (FishSpecies $species): array {
                $data = $species->only(['id', 'common_name', 'scientific_name', 'water_type', 'habitat_requirements', 'source_url']);
                $data['habitat_requirements'] = filled($data['habitat_requirements']) ? trim($data['habitat_requirements']) : null;
                $data['source_url'] = $this->publicSource($data['source_url']);
                $data['occurrence_source_url'] = $this->publicSource($species->pivot->source_url);
                $data['evidence_type'] = in_array($species->pivot->evidence_type, ['aquaculture', 'wild'], true) ? $species->pivot->evidence_type : null;
                $data['sampling_period'] = filled($species->pivot->sampling_period) ? $species->pivot->sampling_period : null;
                $data['publication_year'] = $species->pivot->publication_year ? (int) $species->pivot->publication_year : null;
                $data['compatibility_status'] = 'insufficient_data';

                return $data;
            })->all();

            return [$lake->id => $fish];
        })->all();

        return view('map.index', ['lakes' => $lakes, 'fishByLake' => $fishByLake]);
    }

    private function publicSource(?string $source): ?string
    {
        return is_string($source)
            && filter_var($source, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) parse_url($source, PHP_URL_SCHEME)), ['http', 'https'], true)
                ? $source : null;
    }
}
