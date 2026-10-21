<?php

namespace App\Support;

class ResearchSources
{
    public const GROUPS = [
        'geography' => 'Geography and GIS',
        'measurements' => 'Physical measurements',
        'hydrology' => 'Water type and hydrology',
        'fish' => 'Fish and habitat',
        'history' => 'Historical ecological evidence',
        'photography' => 'Photography and licensing',
    ];

    private array $citations = [];

    private int $uses = 0;

    private array $excluded = [];

    private function __construct(private array $metadata, private string $name) {}

    public static function key(string $url): string
    {
        $document = explode('#', $url, 2)[0];

        return parse_url($document, PHP_URL_QUERY) === null ? rtrim($document, '/') : $document;
    }

    /**
     * @return array{groups: array, citations: array, source_uses: int, duplicates_removed: int, excluded: array}
     */
    public static function build(array $lake, array $profile, array $fish, array $metadata = []): array
    {
        $library = new self($metadata, $lake['name']);
        $geography = $lake['geography'];
        if ($geography) {
            $library->add('geography', $geography['source_url'], ['publisher' => parse_url($geography['source_url'], PHP_URL_HOST) === 'www.openstreetmap.org' ? 'OpenStreetMap contributors' : null],
                'Displayed reference geometry for '.$lake['name'].'. '.$geography['note'].' Source checked '.$geography['checked_on'].'; WGS84 (EPSG:4326).');
            if (isset($geography['license_url'])) {
                $library->add('geography', $geography['license_url'], ['title' => $geography['license'], 'publisher' => 'Open Data Commons'], 'OpenStreetMap attribution and database licensing; not a scientific finding.');
            }
        }
        foreach ($profile['course']['source_objects'] ?? [] as $source) {
            $library->add('geography', $source['url'], ['title' => $source['name'].': OSM '.$source['id'], 'publisher' => 'OpenStreetMap contributors'],
                $source['name'].': OSM '.$source['id'].', revision '.$source['version'].' ('.substr($source['timestamp'], 0, 10).'). Original named-course geometry, not a verified whole-river total.', 'course');
        }
        foreach (['reported_length' => 'Provincial approximate river length', 'surface_area' => 'Historical reported lake surface area', 'water_type_evidence' => 'Water type evidence'] as $key => $purpose) {
            if ($profile[$key]) {
                $source = $profile[$key];
                $library->add($key === 'water_type_evidence' ? 'hydrology' : 'measurements', $source['source_url'], ['title' => $source['reference']],
                    $purpose.' for '.$lake['name'].': '.$source['reference'].' Catalogue caveat (not an additional source finding): '.$source['note']);
            }
        }
        foreach ($fish as $species) {
            $library->add('fish', $species['occurrence_source_url'], ['published_on' => $species['publication_year'] ? (string) $species['publication_year'] : null],
                $species['common_name'].' / '.$species['scientific_name'].' at '.$lake['name'].': '.($species['evidence_type'] ?? 'evidence type unavailable').'; sampled '.($species['sampling_period'] ?? 'period unavailable').'. Historical study context only; current presence and suitability are not established.');
            $library->add('fish', $species['source_url'], [], 'General habitat reference for '.$species['scientific_name'].', not a location-specific occurrence or suitability assessment.');
            foreach ($species['filipino_name_evidence']['sources'] ?? [] as $source) {
                $library->add('fish', $source['url'], $source, 'Filipino common name '.$species['filipino_name_evidence']['name'].' / '.$species['scientific_name'].'. '.$species['filipino_name_evidence']['note'], 'fish_name');
            }
        }
        foreach ($profile['references'] as $source) {
            $key = self::key($source['url']);
            $group = $library->metadata[$key]['group'] ?? ($library->citations[$key]['group'] ?? 'geography');
            $library->add($group, $source['url'], $source, 'Catalogue reference for '.$lake['name'].': '.$source['title'].'. This reference is retained in its original evidence context.');
        }
        foreach (['source_url' => 'Original catalogue provenance', 'official_url' => 'Official location/property reference'] as $field => $purpose) {
            if ($lake[$field]) {
                $key = self::key($lake[$field]);
                $group = $library->metadata[$key]['group'] ?? ($library->citations[$key]['group'] ?? 'geography');
                $library->add($group, $lake[$field], [], $purpose.' for '.$lake['name'].'. This alone does not establish current conditions, safety or habitat suitability.');
            }
        }
        if ($profile['image']) {
            $image = $profile['image'];
            $library->add('photography', $image['source_url'], ['title' => $image['alt'], 'publisher' => $image['author']], 'Photograph attribution: '.$image['author'].'. '.$image['license'].'. Image provenance only; not an environmental observation.');
            $library->add('photography', $image['license_url'], ['title' => $image['license'], 'publisher' => parse_url($image['license_url'], PHP_URL_HOST) === 'creativecommons.org' ? 'Creative Commons' : null], 'Permission/license for the displayed photograph by '.$image['author'].'. Original image and licensing attribution are retained.');
        }

        $groups = array_map(fn (string $label): array => ['label' => $label, 'sources' => []], self::GROUPS);
        foreach ($library->citations as $key => &$citation) {
            $known = $metadata[$key] ?? [];
            foreach (['title', 'publisher', 'published_on'] as $field) {
                if (is_string($known[$field] ?? null) && filled($known[$field])) {
                    $citation[$field] = $known[$field];
                }
            }
            $citation['id'] = 'research-'.$lake['id'].'-'.substr(hash('sha256', $key), 0, 12);
            $citation['year'] = preg_match('/^\d{4}(?:-\d{2}){0,2}$/D', $citation['published_on'] ?? '')
                && parse_url($citation['url'], PHP_URL_HOST) !== 'www.openstreetmap.org' ? substr($citation['published_on'], 0, 4) : null;
            foreach (array_keys($citation['claims']) as $group) {
                $groups[$group]['sources'][] = $key;
            }
        }
        unset($citation);

        return ['groups' => $groups, 'citations' => $library->citations, 'source_uses' => $library->uses,
            'duplicates_removed' => $library->uses - count($library->citations), 'excluded' => $library->excluded];
    }

    private function add(string $group, ?string $url, array $metadata, string $claim, ?string $flag = null): void
    {
        if (! ExplorerProfile::url($url) || ! isset(self::GROUPS[$group])) {
            return;
        }
        $key = self::key($url);
        if (isset($this->metadata[$key]['water_bodies']) && ! in_array($this->name, $this->metadata[$key]['water_bodies'], true)) {
            $this->excluded[$key] = 'Catalogue reference scope does not match this water body; original data retained.';

            return;
        }
        $this->uses++;
        $this->citations[$key] ??= ['url' => $url, 'title' => null, 'publisher' => null, 'published_on' => null,
            'group' => $group, 'claims' => [], 'contexts' => [], 'locations' => [], 'flags' => []];
        $citation = &$this->citations[$key];
        foreach (['title', 'publisher', 'published_on'] as $field) {
            if (is_string($metadata[$field] ?? null) && filled($metadata[$field]) && $citation[$field] === null) {
                $citation[$field] = trim($metadata[$field]);
            }
        }
        $citation['claims'][$group] = array_values(array_unique([...($citation['claims'][$group] ?? []), $claim]));
        if (is_string($metadata['published_on'] ?? null) && filled($metadata['published_on'])) {
            $citation['contexts'] = array_values(array_unique([...$citation['contexts'], $metadata['published_on']]));
        }
        if (str_contains($url, '#')) {
            $citation['locations'] = array_values(array_unique([...$citation['locations'], $url]));
        }
        if ($flag) {
            $citation['flags'][$flag] = true;
        }
    }
}
