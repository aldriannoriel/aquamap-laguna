<?php

namespace Tests\Feature;

use App\Models\PollutionReport;
use App\Models\WaterBody;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExplorerSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ReportCategoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_reporting_and_map_only_expose_supported_categories(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        $response = $this->get(route('reports.create'))->assertOk()->assertSee('Category')->assertDontSee('Natural Springs / Spring Resorts')->assertDontSee('Al Fresco Springs Resort')->assertDontSee('Hidden Valley Springs');
        $this->assertSame(['lake' => 10, 'river' => 5, 'waterfall' => 13], $response->viewData('lakes')->countBy('category')->sortKeys()->all());
        $response->assertSee('optgroup label="Waterfalls"', false);
        $this->get(route('map.index'))->assertOk()->assertDontSee('Al Fresco Springs Resort')->assertDontSee('Hidden Valley Springs');
    }

    #[TestWith(['lake', 'Pandin Lake'])]
    #[TestWith(['river', 'Bumbungan River'])]
    #[TestWith(['waterfall', 'Balon-Balon Falls'])]
    public function test_a_matching_category_saves_the_original_water_body_id_as_pending(string $category, string $name): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        $water = WaterBody::where('name', $name)->sole();
        $form = $this->get(route('reports.create'))->assertOk();
        $this->travel(3)->seconds();
        Storage::fake('pollution-reports');
        $this->post(route('reports.store'), ['photo' => UploadedFile::fake()->image('proof.png'), 'water_body_category' => $category, 'water_body_id' => $water->id, 'pollution_type' => 'other', 'description' => 'Isolated category fixture', 'report_form_token' => $form->viewData('reportFormToken'), 'status' => 'verified'])
            ->assertRedirectToRoute('reports.create')->assertSessionHasNoErrors();
        $report = PollutionReport::sole();
        $this->assertSame($water->id, $report->water_body_id);
        $this->assertSame('pending', $report->status);
        $this->assertNull($report->latitude);
        $this->assertDatabaseCount('report_reviews', 0);
    }

    public function test_mismatched_category_is_rejected_and_selection_and_text_are_retained(): void
    {
        $this->seed(DatabaseSeeder::class);
        $lake = WaterBody::where('name', 'Pandin Lake')->sole();
        $form = $this->get(route('reports.create'))->assertOk();
        $this->travel(3)->seconds();
        $this->post(route('reports.store'), ['water_body_category' => 'waterfall', 'water_body_id' => $lake->id, 'pollution_type' => 'other', 'description' => 'Retained isolated fixture', 'report_form_token' => $form->viewData('reportFormToken')])
            ->assertRedirectToRoute('reports.create')->assertSessionHasErrors('water_body_id')
            ->assertSessionHasInput('water_body_category', 'waterfall')->assertSessionHasInput('water_body_id', $lake->id)->assertSessionHasInput('description', 'Retained isolated fixture');
        $this->assertDatabaseCount('pollution_reports', 0);
    }

    public function test_validation_retry_restores_a_valid_category_water_body_and_observation_type(): void
    {
        $this->seed(DatabaseSeeder::class);
        $lake = WaterBody::where('name', 'Pandin Lake')->sole();
        $form = $this->get(route('reports.create'))->assertOk();
        $this->travel(3)->seconds();
        $this->post(route('reports.store'), ['water_body_category' => 'lake', 'water_body_id' => $lake->id, 'pollution_type' => 'solid_waste', 'description' => '', 'report_form_token' => $form->viewData('reportFormToken')])->assertSessionHasErrors('description');
        $this->get(route('reports.create'))->assertOk()->assertSee('value="lake" selected', false)->assertSee('value="'.$lake->id.'" data-category="lake" selected', false);
        $this->assertDatabaseCount('pollution_reports', 0);
    }
}
