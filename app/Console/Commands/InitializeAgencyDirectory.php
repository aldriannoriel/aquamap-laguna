<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\Referral;
use App\Models\WaterBody;
use App\Support\MunicipalityRouting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InitializeAgencyDirectory extends Command
{
    protected $signature = 'agencies:initialize {--normalize : Normalize existing municipality identities without creating entries}';

    protected $description = 'Initialize provisional test routing once; never recreates deleted recipients';

    public function handle(): int
    {
        $c = DB::connection();
        $isolated = app()->runningUnitTests() && $c->getDriverName() === 'sqlite' && $c->getDatabaseName() === ':memory:';
        if (! $isolated && ($c->getDriverName() !== 'mysql' || $c->getDatabaseName() !== 'aquamap_laguna' || $c->selectOne('SELECT DATABASE() AS name', [], false)->name !== 'aquamap_laguna')) {
            throw new RuntimeException('Agency initialization requires aquamap_laguna or isolated tests.');
        }
        $count = DB::transaction(function (): int {
            if ($this->option('normalize')) {
                $agencies = Agency::orderBy('id')->lockForUpdate()->get();
                $municipalities = MunicipalityRouting::municipalities();
                if ($agencies->groupBy('municipality')->contains(fn ($group) => $group->count() > 1)
                    || $agencies->contains(fn (Agency $a) => ! $municipalities->contains($a->municipality) || filled($a->barangay))) {
                    throw new RuntimeException('Existing recipients require manual duplicate/locality review. Nothing was changed.');
                }
                $changed = 0;
                foreach ($agencies as $agency) {
                    if (str_starts_with($agency->name, 'Provisional referral route: ')) {
                        $agency->name = MunicipalityRouting::label($agency->municipality);
                    }
                    $agency->identity_key = Agency::identityKey($agency->name, $agency->municipality, null);
                    if ($agency->isDirty()) {
                        $agency->save();
                        $changed++;
                    }
                }

                return $changed;
            }
            DB::table('agency_directory_initializations')->insertOrIgnore(['key' => 'provisional-laguna-v1']);
            $marker = DB::table('agency_directory_initializations')->where('key', 'provisional-laguna-v1')->lockForUpdate()->first();
            if ($marker->completed_at !== null) {
                return 0;
            }
            $created = 0;
            foreach (MunicipalityRouting::municipalities() as $municipality) {
                $ids = WaterBody::supported()->where('municipality', $municipality)->pluck('id');
                if ($ids->isEmpty()) {
                    continue;
                }
                $key = Agency::identityKey(MunicipalityRouting::label($municipality), $municipality, null);
                if (Agency::where('identity_key', $key)->exists()) {
                    continue;
                }
                $a = Agency::forceCreate(['name' => MunicipalityRouting::label($municipality), 'municipality' => $municipality, 'email' => Referral::TEST_RECIPIENT,
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
        $this->info($count.($this->option('normalize') ? ' municipality recipients normalized; no entries created or email sent.' : ' provisional test recipients created; no email sent.'));

        return self::SUCCESS;
    }
}
