<?php

namespace App\Support;

class WaterBodyMetrics
{
    /**
     * Calculate supplied segments using great-circle distances on the IUGG
     * mean-radius sphere (6,371,008.8 m), without bridging disconnected parts.
     */
    public static function mappedLength(?array $geography): ?float
    {
        $verified = ExplorerProfile::geography($geography);
        $geometry = $verified['geometry'] ?? null;
        if (! $geometry || ! in_array($geometry['type'], ['LineString', 'MultiLineString'], true)) {
            return null;
        }
        $lines = $geometry['type'] === 'LineString' ? [$geometry['coordinates']] : $geometry['coordinates'];
        $meters = 0.0;
        foreach ($lines as $line) {
            for ($index = 1; $index < count($line); $index++) {
                [$lon1, $lat1] = array_map(deg2rad(...), $line[$index - 1]);
                [$lon2, $lat2] = array_map(deg2rad(...), $line[$index]);
                $haversine = sin(($lat2 - $lat1) / 2) ** 2
                    + cos($lat1) * cos($lat2) * sin(($lon2 - $lon1) / 2) ** 2;
                $meters += 2 * 6371008.8 * asin(sqrt(max(0.0, min(1.0, $haversine))));
            }
        }

        return round($meters / 1000, 3);
    }

    public static function reported(mixed $entry, string $municipality): ?array
    {
        if (! is_array($entry) || ($entry['municipality'] ?? null) !== $municipality
            || ($entry['status'] ?? null) !== 'source_verified'
            || ! is_numeric($entry['value'] ?? null) || (float) $entry['value'] <= 0
            || ! is_finite((float) $entry['value'])
            || ! ExplorerProfile::url($entry['source_url'] ?? null)
            || ! is_string($entry['reference'] ?? null) || ! filled($entry['reference'])
            || ! is_string($entry['note'] ?? null)) {
            return null;
        }

        return array_intersect_key($entry, array_flip(['value', 'source_url', 'reference', 'note']));
    }
}
