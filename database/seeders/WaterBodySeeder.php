<?php

namespace Database\Seeders;

use App\Models\WaterBody;
use Illuminate\Database\Seeder;
use RuntimeException;

class WaterBodySeeder extends Seeder
{
    public function run(): void
    {
        $connection = (new WaterBody)->getConnection();
        $isolatedTest = app()->runningUnitTests()
            && $connection->getDriverName() === 'sqlite'
            && $connection->getDatabaseName() === ':memory:';

        if (! $isolatedTest) {
            if ($connection->getDriverName() !== 'mysql' || $connection->getDatabaseName() !== 'aquamap_laguna') {
                throw new RuntimeException('WaterBodySeeder requires the aquamap_laguna MySQL database.');
            }

            $actualDatabase = $connection->selectOne('SELECT DATABASE() AS name', [], false)->name;

            if ($actualDatabase !== 'aquamap_laguna') {
                throw new RuntimeException('WaterBodySeeder refused an unexpected connected database.');
            }
        }

        $connection->useWriteConnectionWhenReading();

        $connection->transaction(function () use ($connection): void {
            foreach ($this->lakes() as $lake) {
                WaterBody::on($connection->getName())->updateOrCreate(
                    ['name' => $lake['name'], 'municipality' => 'San Pablo City'],
                    $lake,
                );
            }
        });
    }

    /**
     * Published location points, not surveyed centroids. See docs/RESEARCH_SOURCES.md.
     *
     * @return list<array{name: string, water_type: string, latitude: string, longitude: string, description: string, source_url: string}>
     */
    private function lakes(): array
    {
        $source = 'https://nfrdi.da.gov.ph/tpjf/vol32_2/research_article_ortiz_et_al_32220240111.pdf';

        return [
            [
                'name' => 'Sampaloc Lake',
                'water_type' => 'freshwater',
                'latitude' => '14.0791',
                'longitude' => '121.3299',
                'description' => 'Freshwater crater lake in San Pablo City, Laguna. Coordinates are a published lake location point; current water quality and fish suitability have not been established.',
                'source_url' => $source,
            ],
            [
                'name' => 'Pandin Lake',
                'water_type' => 'freshwater',
                'latitude' => '14.116',
                'longitude' => '121.367',
                'description' => 'Freshwater crater lake in San Pablo City, Laguna, adjacent to Yambo Lake. Coordinates are a published lake location point; current water quality and fish suitability have not been established.',
                'source_url' => $source,
            ],
            [
                'name' => 'Yambo Lake',
                'water_type' => 'freshwater',
                'latitude' => '14.1186',
                'longitude' => '121.366',
                'description' => 'Freshwater crater lake associated with San Pablo City, Rizal, and Nagcarlan, Laguna. San Pablo City is the project locality, not an exclusive boundary. Coordinates are a published lake location point; current water quality and fish suitability have not been established.',
                'source_url' => $source,
            ],
        ];
    }
}
