<?php

namespace App\Http\Controllers;

use App\Models\FishSpecies;
use App\Models\WaterBody;
use App\Support\ExplorerProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MapController extends Controller
{
    public function index(): View
    {
        $fields = ['id', 'name', 'municipality', 'water_type', 'latitude', 'longitude', 'description', 'source_url'];

        $metadata = ['category', 'aliases', 'site_type', 'spring_type', 'official_url', 'geography', 'research_references', 'image'];
        $records = WaterBody::query()->select([...$fields, ...$metadata])
            ->whereIn('category', array_keys(WaterBody::CATEGORIES))
            ->where(function (Builder $query): void {
                $query->whereNotNull('geography')->orWhere(function (Builder $legacy): void {
                    $legacy->where('municipality', 'San Pablo City')
                        ->whereIn('name', ['Sampaloc Lake', 'Pandin Lake', 'Yambo Lake']);
                });
            })
            ->withCount(['pollutionReports as verified_report_count' => fn (Builder $query): Builder => $query->where('status', 'verified')])
            ->with(['fishSpecies' => fn (BelongsToMany $query): BelongsToMany => $query
                ->select(['fish_species.id', 'common_name', 'scientific_name', 'water_type', 'habitat_requirements', 'fish_species.source_url'])
                ->orderBy('common_name')])
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $lakes = $records
            ->map(function (WaterBody $lake) use ($fields): array {
                $data = $lake->only($fields);
                $data['description'] = filled($data['description']) ? trim($data['description']) : null;
                $data['source_url'] = $this->publicSource($data['source_url']);
                $data['category'] = $lake->category;
                $data['aliases'] = array_values(array_filter(is_array($lake->aliases) ? $lake->aliases : [], 'is_string'));
                $data['site_type'] = $lake->site_type === 'resort' ? 'resort' : 'water_body';
                $data['spring_type'] = in_array($lake->spring_type, ['geothermal_hot_spring', 'natural_spring'], true) ? $lake->spring_type : null;
                $data['official_url'] = $this->publicSource($lake->official_url);
                $data['geography'] = ExplorerProfile::geography($lake->geography);
                $data['verified_report_count'] = (int) $lake->verified_report_count;

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

        $images = json_decode(file_get_contents(database_path('data/explorer-images.json')), true, flags: JSON_THROW_ON_ERROR);
        $profiles = $records->mapWithKeys(function (WaterBody $lake) use ($images): array {
            $supplement = $images[$lake->name] ?? null;
            $image = $lake->image;
            if ($image === null && is_array($supplement) && ($supplement['municipality'] ?? null) === $lake->municipality) {
                $image = $supplement['image'] ?? null;
            }

            return [$lake->id => [
                'references' => ExplorerProfile::references($lake->research_references),
                'image' => ExplorerProfile::image($image),
            ]];
        })->all();

        return view('map.index', ['lakes' => $lakes, 'fishByLake' => $fishByLake, 'profiles' => $profiles, 'categories' => WaterBody::CATEGORIES]);
    }

    private function publicSource(?string $source): ?string
    {
        return ExplorerProfile::url($source);
    }
}
