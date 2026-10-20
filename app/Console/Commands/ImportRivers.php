<?php

namespace App\Console\Commands;

use App\Support\ExplorerProfile;
use Database\Seeders\ExplorerSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rivers:import')]
#[Description('Add the reviewed missing provincial rivers without overwriting existing profiles')]
class ImportRivers extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ExplorerSeeder $seeder): int
    {
        $data = json_decode(file_get_contents(database_path('data/river-expansion.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($data as $entry) {
            if ($entry['category'] !== 'river' || ($entry['geography'] !== null
                && (! ExplorerProfile::geography($entry['geography'])
                    || ! in_array($entry['geography']['geometry']['type'], ['LineString', 'MultiLineString'], true)))) {
                throw new \RuntimeException('Invalid reviewed river geometry: '.$entry['name']);
            }
        }
        $seeder->import($data);
        $this->info('Reviewed river import complete. Existing records and environmental associations preserved.');

        return self::SUCCESS;
    }
}
