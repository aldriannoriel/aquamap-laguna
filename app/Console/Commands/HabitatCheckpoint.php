<?php

namespace App\Console\Commands;

use App\Models\FishSpecies;
use App\Models\WaterBody;
use App\Models\WaterBodyFish;
use App\Support\ExplorerProfile;
use App\Support\HabitatCatalogue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

#[Signature('habitat:checkpoint {--batch= : Comma-separated IDs of the last applied batch} {--next= : Exact next operation}')]
#[Description('Atomically save actual fish habitat coverage and recovery instructions to project documentation')]
class HabitatCheckpoint extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            HabitatCatalogue::guardedConnection();
            $data = HabitatCatalogue::load();
            $types = json_decode(file_get_contents(database_path('data/water-body-evidence.json')), true, flags: JSON_THROW_ON_ERROR);
            $waters = WaterBody::supported()->select(['id', 'name', 'category', 'municipality', 'water_type'])->with('fishSpecies')->get()->sortBy('name')->values();
            $rows = [];
            $imagesMissing = [];
            $waterTypes = $possible = $assignments = $explanations = 0;
            foreach ($waters as $water) {
                $type = ExplorerProfile::waterType($types[$water->name] ?? null, $water->municipality, $water->category);
                $assessment = HabitatCatalogue::assessment($water, $data);
                $complete = $assessment && $assessment['complete'];
                $waterTypes += (int) ($type !== null);
                $possible += (int) $complete;
                $explanations += (int) $complete;
                $candidates = $assessment['candidates'] ?? [];
                $assignments += count($candidates);
                $missing = array_column(array_filter($candidates, fn (array $fish): bool => $fish['image'] === null), 'common_name');
                if ($missing !== []) {
                    $imagesMissing[] = '#'.$water->id.' '.$water->name.': '.implode(', ', $missing);
                }
                $rows[] = '| '.$water->id.' | '.$water->name.' | '.($type ? 'Reviewed' : 'Pending').' | '.($complete ? count($candidates).' candidates' : 'Pending').' | '.(count($candidates) - count($missing)).'/'.count($candidates).' |';
            }
            $pending = $waters->filter(fn (WaterBody $water): bool => ! ($assessment = HabitatCatalogue::assessment($water, $data)) || ! $assessment['complete']);
            $photoCount = count(array_filter($data['species'], fn (array $fish): bool => ExplorerProfile::image($fish['image'] ?? null, 'fish') !== null));
            $head = (new Process(['git', 'rev-parse', '--short', 'HEAD'], base_path()))->mustRun()->getOutput();
            $status = (new Process(['git', 'status', '--short'], base_path()))->mustRun()->getOutput();
            $validationPath = storage_path('app/fish-habitat/validation.json');
            $validation = is_file($validationPath) ? json_decode(file_get_contents($validationPath), true, flags: JSON_THROW_ON_ERROR) : null;
            $lastTests = $validation ? $validation['summary'].'; '.$validation['checked_on'] : 'Baseline 4f4f1b2: full 327 tests / 4,607 assertions; no new validation recorded yet.';
            $validated = $validation && ($validation['catalogue_hash'] ?? null) === hash_file('sha256', database_path('data/fish-habitat.json'));
            $final = $possible === 53 && $validated && ($validation['complete'] ?? false) === true;
            $next = $this->option('next') ?: ($pending->isNotEmpty()
                ? 'Inspect and validate the next 3–5 pending bodies, then apply only that batch and save this checkpoint again.'
                : ($final ? 'Implementation complete. Preserve this checkpoint and local commits; no imports or migrations remain. Verified replacement photos may be added separately when available.'
                    : 'Run final preservation checks, Laravel tests, Pint, production build and desktop/mobile gallery verification; commit locally without pushing.'));
            $batchIds = array_filter(explode(',', (string) $this->option('batch')), fn (string $id): bool => ctype_digit($id));
            $batch = $waters->whereIn('id', array_map(intval(...), $batchIds))->map(fn (WaterBody $water): string => '#'.$water->id.' '.$water->name)->implode('; ');
            $references = array_sum(array_map(fn (array $fish): int => count($fish['references']), $data['species']));
            $markdown = '# Fish & Habitat crash recovery checkpoint'."\n\n".'Updated: '.now('UTC')->toIso8601String()."\n\n"
                .'## Actual state'."\n\n"
                .'- Phase: '.($final ? '53-body implementation and final validation complete; documented photo placeholders remain.' : ($possible === 53 ? '53-body implementation complete; final validation/preservation review.' : 'Incremental implementation; completed batches retained.'))."\n"
                .'- Git HEAD when saved: '.trim($head).'. A later checkpoint commit may contain this file; git log -1 is authoritative.'."\n"
                .'- Last successful operation: inspected actual catalogue/relationships and saved this checkpoint'.($batch ? '; applied batch '.$batch : '').'.'."\n"
                .'- Water Type reviewed/display coverage: '.$waterTypes.'/53; possible fish coverage: '.$possible.'/53; habitat explanation coverage: '.$explanations.'/53.'."\n"
                .'- Inventory: '.$waters->count().' supported; '.json_encode($waters->countBy('category')).'; '.WaterBody::count().' stored including two preserved hidden legacy properties.'."\n"
                .'- Fish: '.FishSpecies::count().' stored species; '.$assignments.' reviewed possible assignments; '.WaterBodyFish::where('evidence_type', 'potential_habitat')->count().' new potential-only pivots. Original occurrence rows are preserved separately.'."\n"
                .'- Fish photographs installed: '.$photoCount.'/'.count($data['species']).' species. Missing photos use labelled placeholders, never another species image.'."\n"
                .'- References: '.$references.' species-habitat references reviewed; every complete candidate has an explanation, requirements, limitations and source links. Existing research/naming/occurrence citations remain.'."\n"
                .'- Database changes: only explicit additive habitat batches and missing water_type labels. No water body added/deleted, schema operation, report/agency/referral/GIS/photo modification or live email.'."\n"
                .'- Last successful tests: '.$lastTests.'. Current catalogue '.($validated ? 'matches' : 'still needs').' recorded feature validation.'."\n\n"
                .'## Completed and pending IDs/names'."\n\n".'| ID | Water body | Water Type | Possible Fish | Photos |'."\n".'| --- | --- | --- | --- | --- |'."\n".implode("\n", $rows)."\n\n"
                .'Pending Possible Fish: '.($pending->isEmpty() ? 'None.' : $pending->map(fn (WaterBody $water): string => '#'.$water->id.' '.$water->name)->implode('; '))."\n\n"
                .'## Photo gaps and known issues'."\n\n"
                .($imagesMissing === [] ? 'No installed candidate photo gaps yet (unimplemented bodies are still pending).' : implode("\n", array_map(fn (string $item): string => '- '.$item, $imagesMissing)))."\n\n"
                .'Wikimedia returned HTTP 429 after five originals; no further image requests were made. Missing or unusable photos retain labelled placeholders. The misleading Mindanao catfish file was rejected; the downloaded native-hito photograph was also excluded after visual review because prepared fish portions do not provide useful species identification. Originals are preserved in ignored recovery storage. The written three-column specification is used because no draw.io file is present. Large geography SELECT * with SQL sorting hit MySQL error 1038; use minimal columns/PHP sorting.'."\n\n"
                .'## Exact next action'."\n\n".$next."\n\n"
                .'## Recovery protocol'."\n\n"
                .'Read this file, git status/diff and actual rows before resuming. Files/database override stale text. Never discard uncommitted work or automatically rerun migrations/imports. habitat:import previews by default; --apply accepts only 3–5 names, locks the shared inventory, preserves existing species/occurrence rows and writes this checkpoint after success. Repeating a batch must change no rows/timestamps. Save after every batch and commit validated milestones locally; never push. Original public rows and protected-table/.env hashes are in ignored storage/app/fish-habitat/before.json; source metadata, originals and validation evidence remain ignored. Never commit .env or recovery artifacts.'."\n\n"
                .'## Modified files when saved'."\n\n```text\n".trim($status)."\n```\n";
            File::replace(base_path('docs/FISH_HABITAT_CHECKPOINT.md'), $markdown);
            if ($batch && ! app()->runningUnitTests()) {
                $marker = 'Habitat batch IDs: '.implode(',', $batchIds);
                $path = base_path('docs/EXPANSION_PROGRESS.md');
                if (! str_contains(file_get_contents($path), $marker)) {
                    File::append($path, "\n\n".$marker.' — '.$batch.'. Coverage: Water Type '.$waterTypes.'/53; Possible Fish '.$possible.'/53. Checkpoint saved; validate before the local milestone commit.'."\n");
                }
            }
            $this->line(json_encode(['checkpoint' => 'docs/FISH_HABITAT_CHECKPOINT.md', 'water_type' => $waterTypes, 'possible_fish' => $possible,
                'candidate_assignments' => $assignments, 'species_photos' => $photoCount, 'pending' => $pending->count(),
                'completed_names' => $waters->reject(fn (WaterBody $water): bool => $pending->contains('id', $water->id))->pluck('name')->values()->all()], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Checkpoint not replaced. Inspect the project and guarded database; existing checkpoint is preserved. Error type: '.$exception::class);

            return self::FAILURE;
        }
    }
}
