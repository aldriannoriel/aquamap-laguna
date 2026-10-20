<?php

namespace App\Http\Controllers;

use App\Models\FishSpecies;
use App\Models\PollutionReport;
use App\Models\WaterBody;
use App\Support\ExplorerProfile;
use App\Support\RiverCourseCatalogue;
use App\Support\WaterBodyMetrics;
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
            ->whereIn('category', array_keys(WaterBody::PUBLIC_CATEGORIES))
            ->where(function (Builder $query): void {
                $query->whereNotNull('geography')->orWhere('category', 'river')->orWhere(function (Builder $legacy): void {
                    $legacy->where('municipality', 'San Pablo City')
                        ->whereIn('name', ['Sampaloc Lake', 'Pandin Lake', 'Yambo Lake']);
                });
            })
            ->with(['fishSpecies' => fn (BelongsToMany $query): BelongsToMany => $query
                ->select(['fish_species.id', 'common_name', 'scientific_name', 'water_type', 'habitat_requirements', 'fish_species.source_url'])
                ->orderBy('common_name')])
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $reportCategories = PollutionReport::TYPES + ['uncategorized' => 'Other legacy category'];
        $reportCounts = PollutionReport::query()
            ->where('status', 'verified')
            ->whereIn('water_body_id', $records->modelKeys())
            ->select(['water_body_id', 'pollution_type'])
            ->selectRaw('COUNT(*) as report_count')
            ->groupBy('water_body_id', 'pollution_type')
            ->toBase()->get()
            ->groupBy('water_body_id');
        $reportsByLake = $records->mapWithKeys(function (WaterBody $lake) use ($reportCategories, $reportCounts): array {
            $counts = array_fill_keys(array_keys($reportCategories), 0);
            foreach ($reportCounts->get($lake->id, collect()) as $reportCount) {
                $category = array_key_exists($reportCount->pollution_type, PollutionReport::TYPES) ? $reportCount->pollution_type : 'uncategorized';
                $counts[$category] += (int) $reportCount->report_count;
            }

            return [$lake->id => $counts];
        });

        $courseCatalogue = RiverCourseCatalogue::load();
        $displayGeographies = $records->mapWithKeys(fn (WaterBody $lake): array => [$lake->id => RiverCourseCatalogue::resolve($lake, $courseCatalogue)])->all();

        $lakes = $records
            ->map(function (WaterBody $lake) use ($fields, $reportsByLake, $displayGeographies): array {
                $data = $lake->only($fields);
                $data['description'] = filled($data['description']) ? trim($data['description']) : null;
                $data['source_url'] = $this->publicSource($data['source_url']);
                $data['category'] = $lake->category;
                $data['aliases'] = array_values(array_filter(is_array($lake->aliases) ? $lake->aliases : [], 'is_string'));
                $data['site_type'] = $lake->site_type === 'resort' ? 'resort' : 'water_body';
                $data['spring_type'] = in_array($lake->spring_type, ['geothermal_hot_spring', 'natural_spring'], true) ? $lake->spring_type : null;
                $data['official_url'] = $this->publicSource($lake->official_url);
                $data['geography'] = $displayGeographies[$lake->id]['geography'];
                if (($displayGeographies[$lake->id]['course']['coverage'] ?? null) === 'available_named_course') {
                    $data['description'] = $displayGeographies[$lake->id]['course']['note'];
                }
                $data['verified_report_count'] = array_sum($reportsByLake[$lake->id]);

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
        $metrics = json_decode(file_get_contents(database_path('data/water-body-metrics.json')), true, flags: JSON_THROW_ON_ERROR);
        $replacements = json_decode(file_get_contents(database_path('data/waterfall-photos.json')), true, flags: JSON_THROW_ON_ERROR);
        $profiles = $records->mapWithKeys(function (WaterBody $lake) use ($images, $replacements, $reportsByLake, $metrics, $displayGeographies): array {
            $supplement = $images[$lake->name] ?? null;
            $image = $lake->image;
            if ($image === null && is_array($supplement) && ($supplement['municipality'] ?? null) === $lake->municipality) {
                $image = $supplement['image'] ?? null;
            }
            $replacement = $replacements[$lake->name] ?? null;
            if ($lake->category === 'waterfall' && is_array($replacement) && ($replacement['municipality'] ?? null) === $lake->municipality) {
                $replacementImage = $replacement['image'] ?? null;
                if (is_array($replacementImage) && ($replacementImage['license_url'] ?? null) === '/images/water-bodies/document-photo-permission.html') {
                    $replacementImage['license_url'] = url($replacementImage['license_url']);
                }
                $image = ExplorerProfile::image($replacementImage) ?? $image;
            }

            return [$lake->id => [
                'references' => ExplorerProfile::references($lake->research_references),
                'image' => ExplorerProfile::image($image),
                'report_categories' => $reportsByLake[$lake->id],
                'reported_length' => $lake->category === 'river' ? WaterBodyMetrics::reported($metrics['rivers'][$lake->name] ?? null, $lake->municipality) : null,
                'length_note' => $lake->category === 'river' && $lake->name === 'Bumbungan River'
                    && $lake->municipality === $metrics['rivers']['Bumbungan River']['municipality'] ? $metrics['rivers']['Bumbungan River']['note'] : null,
                'mapped_length_km' => $lake->category === 'river' ? WaterBodyMetrics::mappedLength($displayGeographies[$lake->id]['geography']) : null,
                'course' => $displayGeographies[$lake->id]['course'],
                'original_geography_note' => $lake->category === 'river' && $displayGeographies[$lake->id]['course'] !== null
                    && is_string($lake->geography['note'] ?? null) ? $lake->geography['note'] : null,
                'surface_area' => $lake->category === 'lake' ? WaterBodyMetrics::reported($metrics['lakes'][$lake->name] ?? null, $lake->municipality) : null,
            ]];
        })->all();

        return view('map.index', ['lakes' => $lakes, 'fishByLake' => $fishByLake, 'profiles' => $profiles, 'categories' => WaterBody::PUBLIC_CATEGORIES, 'reportCategories' => $reportCategories]);
    }

    private function publicSource(?string $source): ?string
    {
        return ExplorerProfile::url($source);
    }
}
