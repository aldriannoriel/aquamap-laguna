<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\Referral;
use App\Models\WaterBody;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InitializeAgencyDirectory extends Command
{
    protected $signature = 'agencies:initialize';

    protected $description = 'Initialize provisional test routing once; never recreates deleted recipients';

    public function handle(): int
    {
        $c = DB::connection();
        $isolated = app()->runningUnitTests() && $c->getDriverName() === 'sqlite' && $c->getDatabaseName() === ':memory:';
        if (! $isolated && ($c->getDriverName() !== 'mysql' || $c->getDatabaseName() !== 'aquamap_laguna' || $c->selectOne('SELECT DATABASE() AS name', [], false)->name !== 'aquamap_laguna')) {
            throw new RuntimeException('Agency initialization requires aquamap_laguna or isolated tests.');
        }
        $count = DB::transaction(function (): int {
            DB::table('agency_directory_initializations')->insertOrIgnore(['key' => 'provisional-laguna-v1']);
            $marker = DB::table('agency_directory_initializations')->where('key', 'provisional-laguna-v1')->lockForUpdate()->first();
            if ($marker->completed_at !== null) {
                return 0;
            }
            $created = 0;
            foreach (["Los Ba\u{00f1}os", 'San Pablo City', 'Cavinti', 'Pangil', 'Luisiana', 'Siniloan', 'Majayjay'] as $municipality) {
                $ids = WaterBody::supported()->where('municipality', $municipality)->pluck('id');
                if ($ids->isEmpty()) {
                    continue;
                }
                $key = Agency::identityKey('Provisional referral route: '.$municipality, $municipality, null);
                if (Agency::where('identity_key', $key)->exists()) {
                    continue;
                }
                $a = Agency::forceCreate(['name' => 'Provisional referral route: '.$municipality, 'municipality' => $municipality, 'email' => Referral::TEST_RECIPIENT,
                    'active' => true, 'verification_status' => 'provisional', 'identity_key' => $key,
                    'notes' => 'Test routing only. No official agency identity, barangay responsibility or jurisdiction is verified. Associations use existing exact locality labels.']);
                $a->waterBodies()->sync($ids);
                $created++;
            }
            if ($created === 0) {
                throw new RuntimeException('No eligible locality records; initialization was not marked complete.');
            }
            DB::table('agency_directory_initializations')->where('key', 'provisional-laguna-v1')->update(['completed_at' => now()]);

            return $created;
        });
        $this->info($count.' provisional test recipients created; no email sent.');

        return self::SUCCESS;
    }
}
