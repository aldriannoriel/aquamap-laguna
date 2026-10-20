<?php

namespace App\Support;

class WaterBodyMetrics
{
    /**
     * Sum WGS84 ellipsoidal geodesics along the supplied segments, without
     * bridging disconnected parts. Vincenty inverse: Survey Review 23(176),
     * 1975, equations 3, 4, 6 and 13-21. An unconverged segment stays unknown.
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
                $distance = self::segmentMeters($line[$index - 1], $line[$index]);
                if ($distance === null) {
                    return null;
                }
                $meters += $distance;
            }
        }

        return round($meters / 1000, 3);
    }

    private static function segmentMeters(array $start, array $end): ?float
    {
        if ($start == $end) {
            return 0.0;
        }
        $majorAxis = 6378137.0;
        $flattening = 1 / 298.257223563;
        $minorAxis = $majorAxis * (1 - $flattening);
        $reducedLatitude1 = atan((1 - $flattening) * tan(deg2rad($start[1])));
        $reducedLatitude2 = atan((1 - $flattening) * tan(deg2rad($end[1])));
        $sinLatitude1 = sin($reducedLatitude1);
        $cosLatitude1 = cos($reducedLatitude1);
        $sinLatitude2 = sin($reducedLatitude2);
        $cosLatitude2 = cos($reducedLatitude2);
        $longitude = deg2rad(fmod($end[0] - $start[0] + 540, 360) - 180);
        $lambda = $longitude;
        $converged = false;
        for ($iteration = 0; $iteration < 100; $iteration++) {
            $sinLambda = sin($lambda);
            $cosLambda = cos($lambda);
            $sinSigma = sqrt(($cosLatitude2 * $sinLambda) ** 2
                + ($cosLatitude1 * $sinLatitude2 - $sinLatitude1 * $cosLatitude2 * $cosLambda) ** 2);
            if ($sinSigma === 0.0) {
                return null;
            }
            $cosSigma = $sinLatitude1 * $sinLatitude2 + $cosLatitude1 * $cosLatitude2 * $cosLambda;
            $sigma = atan2($sinSigma, $cosSigma);
            $sinAlpha = $cosLatitude1 * $cosLatitude2 * $sinLambda / $sinSigma;
            $cosSquaredAlpha = max(0.0, 1 - $sinAlpha ** 2);
            $cosDoubleSigma = $cosSquaredAlpha > 1e-15 ? $cosSigma - 2 * $sinLatitude1 * $sinLatitude2 / $cosSquaredAlpha : 0.0;
            $coefficient = $flattening / 16 * $cosSquaredAlpha * (4 + $flattening * (4 - 3 * $cosSquaredAlpha));
            $previousLambda = $lambda;
            $lambda = $longitude + (1 - $coefficient) * $flattening * $sinAlpha
                * ($sigma + $coefficient * $sinSigma * ($cosDoubleSigma + $coefficient * $cosSigma * (-1 + 2 * $cosDoubleSigma ** 2)));
            if (abs($lambda - $previousLambda) < 1e-12) {
                $converged = true;
                break;
            }
        }
        if (! $converged) {
            return null;
        }
        $squaredParameter = $cosSquaredAlpha * ($majorAxis ** 2 - $minorAxis ** 2) / $minorAxis ** 2;
        $factorA = 1 + $squaredParameter / 16384 * (4096 + $squaredParameter * (-768 + $squaredParameter * (320 - 175 * $squaredParameter)));
        $factorB = $squaredParameter / 1024 * (256 + $squaredParameter * (-128 + $squaredParameter * (74 - 47 * $squaredParameter)));
        $correction = $factorB * $sinSigma * ($cosDoubleSigma + $factorB / 4
            * ($cosSigma * (-1 + 2 * $cosDoubleSigma ** 2) - $factorB / 6 * $cosDoubleSigma
                * (-3 + 4 * $sinSigma ** 2) * (-3 + 4 * $cosDoubleSigma ** 2)));

        return $minorAxis * $factorA * ($sigma - $correction);
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
