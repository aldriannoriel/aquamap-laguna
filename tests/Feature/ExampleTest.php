<?php

namespace Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_renders_the_landing_page_with_existing_navigation_without_database_queries(): void
    {
        $this->withoutVite();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $response = $this->get(route('home'));

        $response->assertOk()->assertViewIs('landing')
            ->assertSeeText('Discover')
            ->assertSeeText("Laguna's Waters.", false)
            ->assertSee('href="'.route('map.index').'"', false)
            ->assertSee('href="'.route('reports.create').'"', false)
            ->assertSee('href="'.route('admin.dashboard').'"', false)
            ->assertDontSee('id="lake-map"', false)
            ->assertDontSee('id="lake-data"', false);
        $this->assertSame([], $queries);
    }
}
