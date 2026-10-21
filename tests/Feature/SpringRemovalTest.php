<?php

namespace Tests\Feature;

use App\Models\PollutionReport;
use App\Models\WaterBody;
use App\Models\WaterBodyFish;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExplorerSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class SpringRemovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    #[TestWith(['spring', 'resort', 'natural_spring'])]
    #[TestWith(['springs', 'water_body', null])]
    #[TestWith(['resort', 'resort', null])]
    #[TestWith(['hot_spring', 'water_body', null])]
    #[TestWith(['Natural Springs', 'water_body', null])]
    #[TestWith(['lake', 'resort', null])]
    #[TestWith(['river', 'water_body', 'natural_spring'])]
    public function test_legacy_records_and_related_data_are_preserved_but_hidden_and_reject_new_reports(string $category, string $siteType, ?string $springType): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        $id = DB::table('water_bodies')->insertGetId([
            'name' => 'Legacy spring property', 'municipality' => 'Legacy fixture', 'water_type' => 'Not assessed',
            'category' => $category, 'site_type' => $siteType, 'spring_type' => $springType,
            'research_references' => json_encode([['url' => 'https://example.test/archived', 'title' => 'Archived legacy reference']]),
        ]);
        PollutionReport::forceCreate(['water_body_id' => $id, 'pollution_type' => 'other', 'description' => 'Preserved private legacy report']);
        WaterBodyFish::forceCreate(['water_body_id' => $id, 'fish_species_id' => WaterBodyFish::firstOrFail()->fish_species_id]);
        $tables = ['water_bodies', 'fish_species', 'water_body_fish', 'pollution_reports', 'report_reviews'];
        $before = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();

        $map = $this->get(route('map.index'))->assertOk()->assertDontSee('Legacy spring property')
            ->assertDontSee('Archived legacy reference')->assertDontSee('Preserved private legacy report')
            ->assertDontSee('data-category="spring"', false)->assertDontSee('data-category="resort"', false);
        $this->assertCount(28, $map->viewData('lakes'));
        $this->assertArrayNotHasKey($id, $map->viewData('profiles'));
        $form = $this->get(route('reports.create'))->assertOk()->assertDontSee('Legacy spring property');
        $this->assertCount(28, $form->viewData('lakes'));
        Storage::fake('pollution-reports');
        $this->travel(3)->seconds();
        $this->postJson(route('reports.store'), [
            'water_body_category' => 'lake', 'water_body_id' => $id, 'pollution_type' => 'other',
            'description' => 'Rejected legacy submission', 'photo' => UploadedFile::fake()->image('proof.png'),
            'report_form_token' => $form->viewData('reportFormToken'),
        ])->assertUnprocessable()->assertJsonValidationErrors('water_body_id');

        $this->assertSame($before, collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all());
        $this->assertSame([], Storage::disk('pollution-reports')->allFiles());
    }

    #[TestWith(['spring', 'water_body', null])]
    #[TestWith(['resort', 'resort', null])]
    #[TestWith(['lake', 'resort', null])]
    #[TestWith(['lake', 'water_body', 'geothermal_hot_spring'])]
    public function test_new_model_records_cannot_reintroduce_unsupported_categories_or_properties(string $category, string $siteType, ?string $springType): void
    {
        try {
            WaterBody::forceCreate(['name' => 'Rejected property', 'category' => $category, 'site_type' => $siteType, 'spring_type' => $springType]);
            $this->fail('Unsupported records must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('category', $exception->errors());
        }

        $this->assertDatabaseCount('water_bodies', 0);
    }

    public function test_import_preflight_refuses_an_entire_batch_before_inserting_a_legacy_property(): void
    {
        $entry = json_decode(file_get_contents(database_path('data/explorer.json')), true, flags: JSON_THROW_ON_ERROR)[0];
        try {
            (new ExplorerSeeder)->import([$entry, [...$entry, 'name' => 'Rejected resort', 'site_type' => 'resort']]);
            $this->fail('The whole batch must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Only lakes, rivers and waterfalls may be imported.', $exception->getMessage());
        }

        $this->assertDatabaseCount('water_bodies', 0);
    }
}
