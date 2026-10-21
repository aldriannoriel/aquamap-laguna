<?php

namespace App\Console\Commands;

use App\Support\ExplorerProfile;
use Database\Seeders\ExplorerSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lakes:import')]
#[Description('Add reviewed lake additions without overwriting existing records or duplicating aliases')]
class ImportLakes extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ExplorerSeeder $seeder): int
    {
        $data = json_decode(file_get_contents(database_path('data/lake-additions.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($data as $entry) {
            if ($entry['category'] !== 'lake' || ! ExplorerProfile::geography($entry['geography'], 'lake')
                || ! in_array($entry['geography']['geometry']['type'], ['Polygon', 'MultiPolygon'], true)) {
                throw new \RuntimeException('Invalid reviewed lake geometry: '.$entry['name']);
            }
        }
        $seeder->import($data);
        $this->info('Reviewed lake import complete. Existing records and associations preserved.');

        return self::SUCCESS;
    }
}
