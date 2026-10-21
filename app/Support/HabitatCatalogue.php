<?php

namespace App\Support;

use App\Models\FishSpecies;
use App\Models\WaterBody;
use App\Models\WaterBodyFish;
use Illuminate\Database\Connection;
use RuntimeException;

class HabitatCatalogue
{
    public static function load(): array
    {
        $data = json_decode(file_get_contents(database_path('data/fish-habitat.json')), true, flags: JSON_THROW_ON_ERROR);
        if (($data['version'] ?? null) !== 1 || count($data['inventory'] ?? []) !== 53) {
            throw new RuntimeException('The reviewed habitat inventory must contain exactly 53 water bodies.');
        }
        foreach ($data['species'] as $key => $species) {
            if (! preg_match('/\A[a-z]+\z/', $key) || ! filled($species['scientific_name'] ?? null)
                || ! filled($species['habitat_requirements'] ?? null) || ! ExplorerProfile::url($species['source_url'] ?? null)
                || ExplorerProfile::references($species['references'] ?? null) === []) {
                throw new RuntimeException('Invalid habitat species or reference: '.$key);
            }
        }
        $identities = array_map(fn (array $species): string => strtolower(trim($species['scientific_name'])), $data['species']);
        if (count(array_unique($identities)) !== count($identities)) {
            throw new RuntimeException('Duplicate species identity in the habitat catalogue.');
        }
        foreach ($data['water_bodies'] as $name => $entry) {
            if (($data['inventory'][$name] ?? null) !== ['municipality' => $entry['municipality'] ?? null, 'category' => $entry['category'] ?? null]
                || ($entry['evidence_classification'] ?? null) !== 'preliminary_habitat_estimate'
                || ! filled($entry['habitat_explanation'] ?? null) || ! filled($entry['limitations'] ?? null)
                || ($entry['candidates'] ?? []) === []) {
                throw new RuntimeException('Incomplete habitat assessment: '.$name);
            }
            foreach ($entry['candidates'] as $key => $explanation) {
                if (! isset($data['species'][$key]) || ! is_string($explanation) || trim($explanation) === '') {
                    throw new RuntimeException('Invalid habitat candidate: '.$name);
                }
            }
        }

        return $data;
    }

    public static function guardedConnection(): Connection
    {
        $connection = (new WaterBody)->getConnection();
        $isolated = app()->runningUnitTests() && $connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:';
        if (! $isolated && ($connection->getDriverName() !== 'mysql' || $connection->getDatabaseName() !== 'aquamap_laguna'
            || $connection->selectOne('SELECT DATABASE() AS name', [], false)->name !== 'aquamap_laguna')) {
            throw new RuntimeException('Habitat maintenance requires aquamap_laguna or isolated in-memory tests.');
        }
        $connection->useWriteConnectionWhenReading();

        return $connection;
    }

    /** Preserve documented pivots; the separate reviewed matrix also assesses those same species as possible candidates. */
    public static function import(array $names, bool $apply): array
    {
        $data = self::load();
        if (count($names) < 3 || count($names) > 5 || count(array_unique($names)) !== count($names)) {
            throw new RuntimeException('Choose 3–5 distinct water bodies per checkpointed batch.');
        }
        $types = json_decode(file_get_contents(database_path('data/water-body-evidence.json')), true, flags: JSON_THROW_ON_ERROR);

        return self::guardedConnection()->transaction(function () use ($names, $apply, $data, $types): array {
            // The same catalogue rows serialize concurrent importers before any species/pivot insert.
            $waters = WaterBody::supported()->select(['id', 'name', 'municipality', 'category', 'water_type'])->lockForUpdate()->get();
            if ($waters->count() !== 53 || $waters->pluck('name')->unique()->count() !== 53
                || $waters->countBy('category')->sortKeys()->all() !== ['lake' => 11, 'river' => 29, 'waterfall' => 13]) {
                throw new RuntimeException('Supported database inventory differs from the reviewed 11/29/13 inventory.');
            }
            foreach ($waters as $water) {
                $identity = $data['inventory'][$water->name] ?? null;
                if ($identity !== ['municipality' => $water->municipality, 'category' => $water->category]) {
                    throw new RuntimeException('Review the inventory discrepancy before changing '.$water->name.'.');
                }
            }
            $species = FishSpecies::query()->lockForUpdate()->get();
            foreach ($data['species'] as $definition) {
                if ($species->filter(fn (FishSpecies $fish): bool => strtolower(trim($fish->scientific_name)) === strtolower($definition['scientific_name']))->count() > 1) {
                    throw new RuntimeException('Resolve duplicate fish identities before importing.');
                }
            }
            $links = WaterBodyFish::whereIn('water_body_id', $waters->pluck('id'))->lockForUpdate()->get();
            $result = ['applied' => $apply, 'water_types_updated' => 0, 'species_created' => 0, 'links_created' => 0, 'reviewed' => []];
            foreach ($names as $name) {
                $water = $waters->firstWhere('name', $name);
                $entry = $data['water_bodies'][$name] ?? null;
                $type = $water ? ExplorerProfile::waterType($types[$name] ?? null, $water->municipality, $water->category) : null;
                if (! $water || ! $entry || ! $type) {
                    throw new RuntimeException('Complete the reviewed type and habitat entry before importing '.$name.'.');
                }
                $storedType = strtolower(trim((string) $water->water_type));
                $missing = in_array($storedType, ['', 'not assessed', 'not_assessed', 'unknown'], true);
                if (! $missing && $storedType !== $type['type']) {
                    throw new RuntimeException('Preserve the existing classification and review the conflict for '.$name.'.');
                }
                if ($missing) {
                    $result['water_types_updated']++;
                    if ($apply) {
                        $water->forceFill(['water_type' => $type['type']])->save();
                    }
                }
                foreach ($entry['candidates'] as $key => $explanation) {
                    $definition = $data['species'][$key];
                    $fish = $species->first(fn (FishSpecies $fish): bool => strtolower(trim($fish->scientific_name)) === strtolower($definition['scientific_name']));
                    if (! $fish) {
                        $fish = new FishSpecies;
                        $fish->forceFill(['common_name' => $definition['database_name'], 'scientific_name' => $definition['scientific_name'],
                            'water_type' => $definition['water_type'], 'habitat_requirements' => $definition['habitat_requirements'], 'source_url' => $definition['source_url']]);
                        if ($apply) {
                            $fish->save();
                        }
                        $species->push($fish);
                        $result['species_created']++;
                    }
                    $existing = $fish->exists ? $links->first(fn (WaterBodyFish $link): bool => $link->water_body_id === $water->id && $link->fish_species_id === $fish->id) : null;
                    if (! $existing) {
                        $result['links_created']++;
                        if ($apply) {
                            $links->push(WaterBodyFish::forceCreate(['water_body_id' => $water->id, 'fish_species_id' => $fish->id,
                                'compatibility_status' => 'preliminary', 'evidence_type' => 'potential_habitat', 'explanation' => $explanation,
                                'source_url' => $definition['source_url'], 'sampling_period' => null, 'publication_year' => null]));
                        }
                    } elseif ($existing->evidence_type === 'potential_habitat' && ($existing->explanation !== $explanation
                        || $existing->source_url !== $definition['source_url'] || $existing->compatibility_status !== 'preliminary')) {
                        throw new RuntimeException('Existing candidate metadata differs; review it before changing '.$name.'.');
                    }
                }
                $result['reviewed'][] = ['id' => $water->id, 'name' => $name];
            }

            return $result;
        });
    }

    public static function assessment(WaterBody $water, array $data): ?array
    {
        $entry = $data['water_bodies'][$water->name] ?? null;
        if (! $entry || $entry['municipality'] !== $water->municipality || $entry['category'] !== $water->category) {
            return null;
        }
        $candidates = [];
        foreach ($entry['candidates'] as $key => $explanation) {
            $definition = $data['species'][$key];
            $fish = $water->fishSpecies->first(fn (FishSpecies $fish): bool => strtolower(trim($fish->scientific_name)) === strtolower($definition['scientific_name']));
            if (! $fish) {
                continue;
            }
            $image = ExplorerProfile::image($definition['image'] ?? null, 'fish');
            if ($image && ! is_file(public_path(ltrim($image['path'], '/')))) {
                $image = null;
            }
            $candidates[] = ['id' => $fish->id, 'water_body_id' => $water->id, 'common_name' => $definition['common_name'],
                'scientific_name' => $definition['scientific_name'], 'explanation' => $explanation, 'evidence_classification' => $entry['evidence_classification'],
                'habitat_requirements' => $definition['habitat_requirements'], 'limitations' => $definition['limitations'],
                'references' => ExplorerProfile::references($definition['references']), 'image' => $image];
        }

        return [...$entry, 'candidates' => $candidates, 'complete' => count($candidates) === count($entry['candidates'])];
    }
}
