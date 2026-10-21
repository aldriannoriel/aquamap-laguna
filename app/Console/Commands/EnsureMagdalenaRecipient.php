<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\Referral;
use App\Support\MunicipalityRouting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EnsureMagdalenaRecipient extends Command
{
    protected $signature = 'agencies:ensure-magdalena';

    protected $description = 'Add the requested Magdalena test recipient only if absent; preserve all existing edits';

    public function handle(): int
    {
        $connection = DB::connection();
        $isolated = app()->runningUnitTests() && $connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:';
        if (! $isolated && ($connection->getDriverName() !== 'mysql' || $connection->getDatabaseName() !== 'aquamap_laguna'
            || $connection->selectOne('SELECT DATABASE() AS name', [], false)->name !== 'aquamap_laguna')) {
            throw new RuntimeException('Recipient maintenance requires aquamap_laguna or isolated tests.');
        }
        $created = DB::transaction(function (): bool {
            $key = Agency::identityKey('', 'Magdalena', null);
            if (Agency::whereRaw('LOWER(TRIM(municipality)) = ?', ['magdalena'])->orWhere('identity_key', $key)->lockForUpdate()->first() !== null) {
                return false;
            }
            Agency::forceCreate(['name' => MunicipalityRouting::label('Magdalena'), 'municipality' => 'Magdalena',
                'email' => Referral::TEST_RECIPIENT, 'active' => true, 'verification_status' => 'provisional', 'identity_key' => $key,
                'notes' => 'User-requested testing recipient. No official government email or jurisdiction is verified. Incident municipality requires explicit admin review.']);

            return true;
        });
        $this->info($created ? 'Magdalena test recipient added. No email was sent.' : 'Existing Magdalena entry preserved without changes.');

        return self::SUCCESS;
    }
}
