<?php

namespace App\Support;

class ExplorerProfile
{
    public static function url(mixed $value): ?string
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)
            && parse_url($value, PHP_URL_USER) === null && parse_url($value, PHP_URL_PASS) === null
                ? $value : null;
    }

    public static function geography(mixed $value, ?string $category = null): ?array
    {
        if (! is_array($value) || ($value['status'] ?? null) !== 'source_verified'
            || ($value['crs'] ?? null) !== 'EPSG:4326' || ! self::url($value['source_url'] ?? null)
            || ! is_string($value['checked_on'] ?? null)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value['checked_on'])
            || ! checkdate((int) substr($value['checked_on'], 5, 2), (int) substr($value['checked_on'], 8, 2), (int) substr($value['checked_on'], 0, 4))
            || $value['checked_on'] > now('Asia/Manila')->toDateString()
            || ! is_array($value['geometry'] ?? null)) {
            return null;
        }
        $geometry = $value['geometry'];
        if (($category === 'river' && ! in_array($geometry['type'] ?? null, ['Point', 'LineString', 'MultiLineString'], true))
            || ($category === 'lake' && ! in_array($geometry['type'] ?? null, ['Point', 'Polygon', 'MultiPolygon'], true))) {
            return null;
        }
        $coordinates = $geometry['coordinates'] ?? null;
        $valid = match ($geometry['type'] ?? null) {
            'Point' => self::point($coordinates),
            'LineString' => self::line($coordinates),
            'MultiLineString' => self::every($coordinates, self::line(...)),
            'Polygon' => self::every($coordinates, self::ring(...)),
            'MultiPolygon' => self::every($coordinates, fn (mixed $rings): bool => self::every($rings, self::ring(...))),
            default => false,
        };

        $result = $valid ? [
            'status' => 'source_verified', 'crs' => 'EPSG:4326',
            'source_url' => $value['source_url'], 'checked_on' => $value['checked_on'],
            'note' => is_string($value['note'] ?? null) ? $value['note'] : 'Source checked; positional accuracy is unconfirmed.',
            'geometry' => ['type' => $geometry['type'], 'coordinates' => $coordinates],
        ] : null;
        if ($result && parse_url($value['source_url'], PHP_URL_HOST) === 'www.openstreetmap.org') {
            $result['license'] = 'OpenStreetMap contributors, ODbL 1.0';
            $result['license_url'] = 'https://opendatacommons.org/licenses/odbl/1-0/';
        }

        return $result;
    }

    public static function references(mixed $value): array
    {
        $references = [];
        foreach (is_array($value) ? array_slice($value, 0, 20) : [] as $reference) {
            if (! is_array($reference) || ! self::url($reference['url'] ?? null)
                || ! is_string($reference['title'] ?? null) || ! filled($reference['title'])
                || ! is_string($reference['publisher'] ?? null) || ! filled($reference['publisher'])) {
                continue;
            }
            $references[] = [
                'title' => $reference['title'], 'publisher' => $reference['publisher'], 'url' => $reference['url'],
                'published_on' => is_string($reference['published_on'] ?? null) && filled($reference['published_on']) ? $reference['published_on'] : null,
            ];
        }

        return $references;
    }

    public static function referencePoint(mixed $geography): ?array
    {
        $verified = self::geography($geography);
        if (! $verified) {
            return null;
        }
        $point = $verified['geometry']['coordinates'];
        while (is_array($point[0])) {
            $point = $point[0];
        }

        return ['latitude' => $point[1], 'longitude' => $point[0]];
    }

    /**
     * @return array{path: string, alt: string, author: string, license: string, source_url: string, license_url: string, fit?: 'contain'|'cover'}|null
     */
    public static function image(mixed $value): ?array
    {
        if (! is_array($value) || ! is_string($value['path'] ?? null)
            || ! preg_match('~^/images/water-bodies/[a-z0-9-]+\.(jpg|jpeg|png|webp)$~D', $value['path'])
            || ! is_file(public_path($value['path']))
            || ! self::url($value['source_url'] ?? null) || ! self::url($value['license_url'] ?? null)) {
            return null;
        }
        foreach (['author', 'license', 'alt'] as $field) {
            if (! is_string($value[$field] ?? null) || ! filled($value[$field])) {
                return null;
            }
        }

        $image = array_intersect_key($value, array_flip(['path', 'alt', 'author', 'license', 'source_url', 'license_url']));
        if (in_array($value['fit'] ?? null, ['contain', 'cover'], true)) {
            $image['fit'] = $value['fit'];
        }

        return $image;
    }

    private static function point(mixed $point): bool
    {
        return is_array($point) && array_is_list($point) && count($point) === 2
            && (is_int($point[0]) || is_float($point[0])) && (is_int($point[1]) || is_float($point[1]))
            && is_finite((float) $point[0]) && is_finite((float) $point[1])
            && abs($point[0]) <= 180 && abs($point[1]) <= 90;
    }

    private static function line(mixed $line): bool
    {
        return is_array($line) && count($line) >= 2 && self::every($line, self::point(...))
            && count(array_unique(array_map(json_encode(...), $line))) >= 2;
    }

    private static function ring(mixed $ring): bool
    {
        return is_array($ring) && count($ring) >= 4 && self::every($ring, self::point(...))
            && $ring[0] === $ring[array_key_last($ring)];
    }

    private static function every(mixed $items, callable $check): bool
    {
        if (! is_array($items) || ! array_is_list($items) || count($items) < 1 || count($items) > 20000) {
            return false;
        }
        foreach ($items as $item) {
            if (! $check($item)) {
                return false;
            }
        }

        return true;
    }
}
