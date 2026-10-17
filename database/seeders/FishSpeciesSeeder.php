<?php

namespace Database\Seeders;

use App\Models\FishSpecies;
use App\Models\WaterBody;
use App\Models\WaterBodyFish;
use Illuminate\Database\Seeder;
use RuntimeException;

class FishSpeciesSeeder extends Seeder
{
    public function run(): void
    {
        $connection = (new FishSpecies)->getConnection();
        $isolatedTest = app()->runningUnitTests()
            && $connection->getDriverName() === 'sqlite'
            && $connection->getDatabaseName() === ':memory:';

        if (! $isolatedTest) {
            if ($connection->getDriverName() !== 'mysql' || $connection->getDatabaseName() !== 'aquamap_laguna') {
                throw new RuntimeException('FishSpeciesSeeder requires the aquamap_laguna MySQL database.');
            }

            if ($connection->selectOne('SELECT DATABASE() AS name', [], false)->name !== 'aquamap_laguna') {
                throw new RuntimeException('FishSpeciesSeeder refused an unexpected connected database.');
            }
        }

        $connection->useWriteConnectionWhenReading();
        $connection->transaction(function () use ($connection): void {
            $lakes = [];
            foreach (['Sampaloc Lake', 'Pandin Lake', 'Yambo Lake'] as $name) {
                $matches = WaterBody::on($connection->getName())->where('name', $name)->where('municipality', 'San Pablo City')->get();
                if ($matches->count() !== 1) {
                    throw new RuntimeException('Fish seeding requires exactly one existing record for each target lake.');
                }
                $lakes[] = $matches->sole();
            }

            $speciesQuery = FishSpecies::on($connection->getName())->where('scientific_name', 'Oreochromis niloticus');
            if ($speciesQuery->count() > 1) {
                throw new RuntimeException('Resolve duplicate Nile tilapia records before seeding.');
            }

            $fish = $speciesQuery->first() ?? (new FishSpecies)->setConnection($connection->getName());
            $fish->forceFill([
                'scientific_name' => 'Oreochromis niloticus',
                'common_name' => 'Nile tilapia',
                'water_type' => 'freshwater',
                'habitat_requirements' => 'General species biology (FAO, 2009): a tropical fish associated with shallow water and an omnivorous grazing diet. FAO reports a preferred temperature range of 31-36 °C. This preference is not a lake-specific suitability threshold. Current lake conditions and complete life-stage requirements have not been validated.',
                'source_url' => 'https://www.fao.org/fishery/docs/CDrom/aquaculture/I1129m/file/en/en_niletilapia.htm',
            ])->save();

            foreach ($lakes as $lake) {
                $identity = [
                    'water_body_id' => $lake->id,
                    'fish_species_id' => $fish->id,
                ];
                $link = WaterBodyFish::on($connection->getName())->where($identity)->first()
                    ?? (new WaterBodyFish)->setConnection($connection->getName());
                $link->forceFill([
                    ...$identity,
                    'evidence_type' => 'aquaculture',
                    'sampling_period' => 'July-September 2017',
                    'publication_year' => 2019,
                    'source_url' => 'https://link.springer.com/article/10.1007/s00128-019-02685-z',
                    'compatibility_status' => 'insufficient_data',
                    'explanation' => 'Current lake temperature, pH, dissolved oxygen, salinity and seasonal depth profiles have not been validated for a habitat assessment. Historical aquaculture occurrence does not establish present suitability or a wild population.',
                ])->save();
            }
        });
    }
}
