<?php

namespace App\Console\Commands;

use App\Models\WaterBody;
use App\Support\RiverCourseCatalogue;
use App\Support\WaterBodyMetrics;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rivers:audit-courses {--json : Print the reviewed coverage and length audit as JSON}')]
#[Description('Validate source-connected river courses and audit available lengths without changing records')]
class AuditRiverCourses extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = (new WaterBody)->getConnection();
        $isolated = app()->runningUnitTests() && $connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:';
        if (! $isolated && ($connection->getDriverName() !== 'mysql' || $connection->getDatabaseName() !== 'aquamap_laguna'
            || $connection->selectOne('SELECT DATABASE() AS name', [], false)->name !== 'aquamap_laguna')) {
            throw new \RuntimeException('Course audit requires aquamap_laguna or isolated in-memory tests.');
        }
        $catalogue = RiverCourseCatalogue::load();
        $metrics = json_decode(file_get_contents(database_path('data/water-body-metrics.json')), true, flags: JSON_THROW_ON_ERROR);
        $rows = [];
        $invalid = false;
        foreach (WaterBody::where('category', 'river')->orderBy('name')->get() as $river) {
            $resolved = RiverCourseCatalogue::resolve($river, $catalogue);
            $course = $resolved['course'];
            $invalid = $invalid || $course === null;
            $reported = WaterBodyMetrics::reported($metrics['rivers'][$river->name] ?? null, $river->municipality);
            $rows[] = [
                'river' => $river->name, 'coverage' => $course['coverage'] ?? 'catalogue_identity_or_geometry_unverified',
                'source_ways' => count($course['source_objects'] ?? []),
                'baseline_mapped_km' => WaterBodyMetrics::mappedLength($river->geography),
                'mapped_course_km' => WaterBodyMetrics::mappedLength($resolved['geography']),
                'provincial_approximate_km' => $reported['value'] ?? null,
                'coverage_note' => $course['note'] ?? 'No applicable reviewed course entry.',
                'whole_river_length_verified' => false,
            ];
        }
        if ($this->option('json')) {
            $this->line(json_encode(['rivers' => $rows, 'valid' => ! $invalid], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['River', 'Coverage', 'Source ways', 'Before km', 'Mapped km', 'Provincial approx. km', 'Whole total verified'], array_map(fn (array $row): array => [
                $row['river'], $row['coverage'], $row['source_ways'], $row['baseline_mapped_km'] ?? 'Unknown', $row['mapped_course_km'] ?? 'Unknown', $row['provincial_approximate_km'] ?? 'Unknown', 'No',
            ], $rows));
        }

        return $invalid ? self::FAILURE : self::SUCCESS;
    }
}
