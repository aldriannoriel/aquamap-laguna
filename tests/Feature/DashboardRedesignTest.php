<?php

namespace Tests\Feature;

use App\Models\PollutionReport;
use App\Models\WaterBody;
use App\Support\ExplorerProfile;
use Database\Seeders\ExplorerSeeder;
use Database\Seeders\WaterBodySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DashboardRedesignTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_public_dashboard_never_queries_or_projects_report_data(): void
    {
        $this->seed(ExplorerSeeder::class);
        $river = WaterBody::where('category', 'river')->firstOrFail();
        PollutionReport::forceCreate(['water_body_id' => $river->id, 'status' => 'verified', 'pollution_type' => 'other', 'description' => 'Private proof fixture', 'photo_path' => 'private-proof.jpg']);
        $before = PollutionReport::sole()->toArray();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $response = $this->get(route('map.index'))->assertOk()->assertDontSee('Private proof fixture')->assertDontSee('private-proof.jpg')
            ->assertDontSee('Visible Pollution Reports')->assertDontSee('id="map-selection"', false)
            ->assertDontSee("Understand Laguna's aquatic environments", false)->assertSee('class="report-fab"', false);
        $this->assertSame([], array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'pollution_reports'))));
        foreach ($response->viewData('lakes') as $record) {
            $this->assertArrayNotHasKey('verified_report_count', $record);
            $this->assertArrayNotHasKey('report_categories', $response->viewData('profiles')[$record['id']]);
        }
        $this->assertSame($before, PollutionReport::sole()->toArray());
    }

    public function test_new_submissions_require_photo_proof_without_changing_legacy_reports(): void
    {
        Storage::fake('pollution-reports');
        $this->seed(WaterBodySeeder::class);
        $water = WaterBody::firstOrFail();
        $legacy = PollutionReport::forceCreate(['water_body_id' => $water->id, 'pollution_type' => 'other', 'description' => 'Legacy report without photograph', 'photo_path' => null]);
        $before = $legacy->fresh()->toArray();
        $form = $this->get(route('reports.create'))->assertOk()->assertSee('Photo proof')->assertSee('required');
        $this->travel(3)->seconds();
        $payload = ['water_body_id' => $water->id, 'pollution_type' => 'other', 'description' => 'New proofless submission', 'report_form_token' => $form->viewData('reportFormToken')];
        $this->postJson(route('reports.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->post(route('reports.store'), $payload)->assertSessionHasErrors('photo')->assertSessionHasInput('description', $payload['description']);
        $this->assertDatabaseCount('pollution_reports', 1);
        $this->assertSame($before, $legacy->fresh()->toArray());
        $this->assertSame([], Storage::disk('pollution-reports')->allFiles());
    }

    public function test_water_type_requires_exact_location_and_reviewed_evidence(): void
    {
        $this->seed(ExplorerSeeder::class);
        $response = $this->get(route('map.index'))->assertOk();
        $evidence = json_decode(file_get_contents(database_path('data/water-body-evidence.json')), true, flags: JSON_THROW_ON_ERROR);
        $lake = WaterBody::where('name', 'Yambo Lake')->sole();
        $this->assertSame('freshwater', $response->viewData('profiles')[$lake->id]['water_type_evidence']['type']);
        $river = WaterBody::where('category', 'river')->firstOrFail();
        $this->assertSame('general_geographic_assessment', $response->viewData('profiles')[$river->id]['water_type_evidence']['basis']);
        $this->assertSame('freshwater', $response->viewData('profiles')[$river->id]['water_type_evidence']['type']);
        $entry = $evidence['Yambo Lake'];
        $this->assertNull(ExplorerProfile::waterType($entry, 'Unrelated municipality', 'lake'));
        $this->assertNull(ExplorerProfile::waterType($entry, $lake->municipality, 'river'));
        $entry['source_url'] = 'javascript:alert(1)';
        $this->assertNull(ExplorerProfile::waterType($entry, $lake->municipality, 'lake'));
    }

    public function test_public_json_and_profiles_share_reviewed_types_without_changing_legacy_data(): void
    {
        $this->seed(ExplorerSeeder::class);
        $yambo = WaterBody::where('name', 'Yambo Lake')->sole();
        $yambo->update(['water_type' => 'Class C legacy fixture']);
        $river = WaterBody::where('category', 'river')->firstOrFail();
        $river->update(['water_type' => 'freshwater']);
        foreach ([['municipality' => 'Unrelated locality'], ['category' => 'river']] as $mismatch) {
            $copy = $yambo->replicate();
            $copy->forceFill($mismatch)->save();
        }
        $before = WaterBody::orderBy('id')->get()->keyBy('id')->toArray();

        $response = $this->get(route('map.index'))->assertOk();

        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $payload = json_decode($document->getElementById('lake-data')->textContent, true, flags: JSON_THROW_ON_ERROR);
        $evidence = json_decode(file_get_contents(database_path('data/water-body-evidence.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($payload as $record) {
            $entry = $evidence[$record['name']] ?? [];
            $expected = ($entry['municipality'] ?? null) === $record['municipality']
                && ($entry['category'] ?? null) === $record['category'] ? 'freshwater' : null;
            $profile = $response->viewData('profiles')[$record['id']];
            $this->assertSame($expected, $record['water_type'], $record['name']);
            $this->assertSame($record['water_type'], $profile['water_type_evidence']['type'] ?? null);
            $this->assertSame($before[$record['id']]['water_type'], $profile['legacy_classification']);
        }
        $this->assertSame($before, WaterBody::orderBy('id')->get()->keyBy('id')->toArray());
        $response->assertSee('Earlier catalogue classification: Class C legacy fixture');
    }

    #[TestWith(['freshwater', 'Freshwater'])]
    #[TestWith(['brackish', 'Brackish Water'])]
    #[TestWith(['saltwater', 'Saltwater'])]
    #[TestWith(['Class C', 'Water type not yet verified'])]
    #[TestWith([null, 'Water type not yet verified'])]
    public function test_profile_supports_reviewed_salinity_types_and_explicit_unknowns(?string $type, string $label): void
    {
        $this->seed(WaterBodySeeder::class);
        $response = $this->get(route('map.index'))->assertOk();
        $lake = collect($response->viewData('lakes'))->firstWhere('name', 'Yambo Lake');
        $profile = $response->viewData('profiles')[$lake['id']];
        $entry = [
            'status' => 'source_verified', 'municipality' => $lake['municipality'], 'category' => 'lake', 'type' => $type,
            'source_url' => 'https://example.test/hydrology', 'reference' => 'Isolated classification fixture', 'note' => 'Synthetic test evidence only.',
        ];
        $profile['water_type_evidence'] = ExplorerProfile::waterType($entry, $lake['municipality'], 'lake');

        $html = view('map.profile', ['lake' => $lake, 'profile' => $profile, 'categories' => $response->viewData('categories'), 'fishByLake' => []])->render();

        $this->assertSame(in_array($type, ['freshwater', 'brackish', 'saltwater'], true) ? $type : null, $profile['water_type_evidence']['type'] ?? null);
        $this->assertSame(1, preg_match('/<p data-water-type[^>]*>(.*?)<\/p>/s', $html, $matches));
        $this->assertSame($label, trim($matches[1]));
    }
}
