<?php

namespace App\Console\Commands;

use Database\Seeders\ExplorerSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('waterfalls:import')]
#[Description('Import only the reviewed missing waterfall catalogue without migrations or existing-record overwrites')]
class ImportWaterfalls extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ExplorerSeeder $seeder): int
    {
        $data = json_decode(file_get_contents(database_path('data/waterfall-expansion.json')), true, flags: JSON_THROW_ON_ERROR);
        $seeder->import($data);
        $this->info('Reviewed waterfall import complete. Existing names and metadata are preserved.');

        return self::SUCCESS;
    }
}
