<?php

namespace App\Support;

use App\Models\WaterBody;

class RiverCourseCatalogue
{
    public static function load(): array
    {
        return json_decode(file_get_contents(database_path('data/river-courses.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Resolve reviewed display geography while retaining the original database
     * record. An identity/source conflict always falls back to that record.
     *
     * @return array{geography: ?array, course: ?array}
     */
    public static function resolve(WaterBody $waterBody, array $catalogue): array
    {
        $baseline = ExplorerProfile::geography($waterBody->geography, $waterBody->category);
        $fallback = ['geography' => $baseline, 'course' => null];
        $entry = $catalogue['rivers'][$waterBody->name] ?? null;
        if ($waterBody->category !== 'river' || ! is_array($entry)
            || ($entry['municipality'] ?? null) !== $waterBody->municipality
            || ! array_key_exists('baseline_source_url', $entry)
            || $entry['baseline_source_url'] !== ($waterBody->geography['source_url'] ?? null)
            || ! is_string($entry['note'] ?? null)) {
            return $fallback;
        }
        if (($entry['coverage'] ?? null) === 'unmapped' && ($entry['geography'] ?? null) === null) {
            return ['geography' => $baseline, 'course' => [
                'coverage' => 'unmapped', 'note' => $entry['note'], 'source_objects' => [],
                'whole_river_length_verified' => false,
            ]];
        }
        if (($entry['coverage'] ?? null) !== 'available_named_course') {
            return $fallback;
        }
        $geography = ExplorerProfile::geography($entry['geography'] ?? null, 'river');
        if (! $geography || ! in_array($geography['geometry']['type'], ['LineString', 'MultiLineString'], true)) {
            return $fallback;
        }
        $aliases = is_array($waterBody->aliases) ? $waterBody->aliases : [];
        $topology = self::topology($entry, $geography, [$waterBody->name, ...$aliases]);
        if ($topology === null) {
            return $fallback;
        }
        $geography['note'] = $entry['note'];

        return ['geography' => $geography, 'course' => [
            'coverage' => 'available_named_course', 'note' => $entry['note'],
            'source_objects' => $topology['source_objects'], 'endpoint_count' => $topology['endpoint_count'],
            'whole_river_length_verified' => false,
        ]];
    }

    /**
     * Enforce a source-connected, nonduplicated course. A name match alone is
     * insufficient: unrelated rivers can share a name in the same region.
     */
    private static function topology(array $entry, array $geography, array $names): ?array
    {
        $objects = $entry['source_objects'] ?? null;
        $geometry = $geography['geometry'];
        $lines = $geometry['type'] === 'LineString' ? [$geometry['coordinates']] : $geometry['coordinates'];
        if (! is_array($objects) || ! array_is_list($objects) || count($objects) !== count($lines)
            || count($objects) > 200 || ! is_array($entry['seed_way_ids'] ?? null)) {
            return null;
        }
        $positions = [];
        $edges = [];
        $adjacency = [];
        $sourceObjects = [];
        $wayIds = [];
        foreach ($objects as $index => $object) {
            if (! is_array($object) || ! is_int($object['id'] ?? null) || $object['id'] <= 0
                || ! is_int($object['version'] ?? null) || $object['version'] < 1
                || ! in_array($object['name'] ?? null, $names, true)
                || ! in_array($object['waterway'] ?? null, ['river', 'stream'], true)
                || ($object['url'] ?? null) !== 'https://www.openstreetmap.org/way/'.$object['id']
                || ! is_string($object['timestamp'] ?? null)
                || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $object['timestamp'])
                || $object['timestamp'] > $geography['checked_on'].'T23:59:59Z'
                || isset($wayIds[$object['id']]) || ! is_array($object['node_ids'] ?? null)
                || ! array_is_list($object['node_ids']) || count($object['node_ids']) !== count($lines[$index])) {
                return null;
            }
            $wayIds[$object['id']] = true;
            foreach ($object['node_ids'] as $offset => $nodeId) {
                if (! is_int($nodeId) || $nodeId <= 0) {
                    return null;
                }
                $point = $lines[$index][$offset];
                if ($point[0] < 120.8 || $point[0] > 121.8 || $point[1] < 13.8 || $point[1] > 14.8) {
                    return null;
                }
                if (isset($positions[$nodeId]) && $positions[$nodeId] !== $point) {
                    return null;
                }
                $positions[$nodeId] = $point;
                if ($offset === 0) {
                    continue;
                }
                $previous = $object['node_ids'][$offset - 1];
                $edge = min($previous, $nodeId).':'.max($previous, $nodeId);
                if ($previous === $nodeId || isset($edges[$edge])) {
                    return null;
                }
                $edges[$edge] = true;
                $adjacency[$previous][$nodeId] = true;
                $adjacency[$nodeId][$previous] = true;
            }
            $sourceObjects[] = array_intersect_key($object, array_flip(['id', 'version', 'name', 'url', 'timestamp']));
        }
        if (array_intersect(array_keys($wayIds), $entry['seed_way_ids']) === []) {
            return null;
        }
        $pending = [array_key_first($positions)];
        $visited = [];
        while ($pending !== []) {
            $node = array_pop($pending);
            if (isset($visited[$node])) {
                continue;
            }
            $visited[$node] = true;
            foreach (array_keys($adjacency[$node] ?? []) as $neighbor) {
                if (! isset($visited[$neighbor])) {
                    $pending[] = $neighbor;
                }
            }
        }
        if (count($visited) !== count($positions)) {
            return null;
        }
        $endpoints = count(array_filter($adjacency, fn (array $neighbors): bool => count($neighbors) === 1));
        if ($endpoints !== 2 || array_filter($adjacency, fn (array $neighbors): bool => count($neighbors) > 2) !== []) {
            return null;
        }

        return ['source_objects' => $sourceObjects, 'endpoint_count' => $endpoints];
    }
}
