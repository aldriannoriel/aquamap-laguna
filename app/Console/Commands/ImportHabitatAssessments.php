<?php

namespace App\Console\Commands;

use App\Support\HabitatCatalogue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

#[Signature('habitat:import {names* : Three to five reviewed water body names} {--apply : Apply the additive batch; otherwise preview only}')]
#[Description('Import a small reviewed habitat batch without replacing documented fish evidence')]
class ImportHabitatAssessments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $result = HabitatCatalogue::import($this->argument('names'), (bool) $this->option('apply'));
        } catch (\Throwable $exception) {
            $this->error($exception instanceof \RuntimeException && ! $exception instanceof QueryException
                ? $exception->getMessage() : 'Habitat import failed; no batch changes committed. Inspect the data and database connection.');

            return self::FAILURE;
        }
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        if ($result['applied'] && ! app()->runningUnitTests()) {
            $checkpoint = $this->call('habitat:checkpoint', ['--batch' => implode(',', array_column($result['reviewed'], 'id'))]);
            if ($checkpoint !== self::SUCCESS) {
                $this->error('The batch committed, but checkpoint writing failed. Inspect actual records and run habitat:checkpoint before continuing.');

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
