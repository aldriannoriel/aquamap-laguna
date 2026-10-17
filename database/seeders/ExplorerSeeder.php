<?php

namespace Database\Seeders;

use App\Models\WaterBody;
use Illuminate\Database\Seeder;
use RuntimeException;

class ExplorerSeeder extends Seeder
{
    public function run(): void
    {
        $connection = (new WaterBody)->getConnection();
        $isolated = app()->runningUnitTests() && $connection->getDriverName() === 'sqlite'
            && $connection->getDatabaseName() === ':memory:';

        if (! $isolated && ($connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'aquamap_laguna'
            || $connection->selectOne('SELECT DATABASE() AS name', [], false)->name !== 'aquamap_laguna')) {
            throw new RuntimeException('ExplorerSeeder requires aquamap_laguna or isolated in-memory tests.');
        }

        $data = [
            ...json_decode(file_get_contents(database_path('data/explorer.json')), true, flags: JSON_THROW_ON_ERROR),
            ...json_decode(file_get_contents(database_path('data/missing-water-bodies.json')), true, flags: JSON_THROW_ON_ERROR),
        ];
        $connection->useWriteConnectionWhenReading();
        $connection->transaction(function () use ($data, $connection): void {
            foreach ($data as $entry) {
                $names = array_map(mb_strtolower(...), [$entry['name'], ...$entry['aliases']]);
                $matches = WaterBody::query()->whereIn($connection->raw('LOWER(name)'), $names)->lockForUpdate()->get();
                if ($matches->count() > 1 || ($matches->isNotEmpty()
                    && ($matches->first()->municipality !== $entry['municipality'] || $matches->first()->name !== $entry['name']))) {
                    throw new RuntimeException('Ambiguous explorer identity: '.$entry['name']);
                }

                $record = $matches->first();
                if (! $record) {
                    WaterBody::forceCreate($entry);

                    continue;
                }

                foreach (['geography', 'research_references', 'image', 'aliases', 'official_url', 'spring_type'] as $field) {
                    if ($record->{$field} !== null && $record->{$field} != $entry[$field]) {
                        throw new RuntimeException('Existing explorer metadata requires manual review: '.$entry['name']);
                    }
                    if ($record->{$field} === null) {
                        $record->setAttribute($field, $entry[$field]);
                    }
                }
                if ($record->category !== $entry['category'] || $record->site_type !== $entry['site_type']) {
                    throw new RuntimeException('Existing explorer category requires manual review: '.$entry['name']);
                }
                if ($record->isDirty()) {
                    $record->save();
                }
            }
        });
    }
}
