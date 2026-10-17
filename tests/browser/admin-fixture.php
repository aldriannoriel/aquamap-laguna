<?php

// Test-only router/fixture. Never mounted by public/index.php or application routes.
use App\Models\PollutionReport;
use App\Models\User;
use App\Models\WaterBody;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExplorerSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);
$directory = realpath(getenv('AQUAMAP_REPORT_FIXTURE') ?: '');
$allowed = realpath($root.'/storage/app/day5-browser');
if (! $directory || ! $allowed || ! str_starts_with($directory, $allowed.DIRECTORY_SEPARATOR)
    || ! preg_match('/^fixture-[0-9]+$/', basename($directory))) {
    throw new RuntimeException('A confined, isolated browser fixture directory is required.');
}

if (PHP_SAPI === 'cli-server') {
    $asset = realpath($root.'/public'.rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
    if ($asset && str_starts_with($asset, realpath($root.'/public').DIRECTORY_SEPARATOR) && is_file($asset)) {
        return false;
    }
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$setup = PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'setup';
$app->afterBootstrapping(LoadConfiguration::class, function () use ($directory, $setup): void {
    // Apply isolation before providers resolve the rate limiter/cache singleton.
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.url' => null,
        'database.connections.sqlite.database' => $setup ? ':memory:' : $directory.'/browser.sqlite',
        'session.driver' => 'file', 'session.files' => $directory.'/sessions',
        'session.cookie' => 'aquamap_isolated_browser', 'session.secure' => false,
        'cache.default' => 'file', 'cache.stores.file.path' => $directory.'/cache',
        'cache.stores.file.lock_path' => $directory.'/cache',
        'filesystems.disks.pollution-reports.root' => $directory.'/photos',
    ]);
});
$app->make(Kernel::class)->bootstrap();
DB::purge('sqlite');
if (DB::connection()->getDriverName() !== 'sqlite'
    || DB::connection()->getDatabaseName() !== ($setup ? ':memory:' : $directory.'/browser.sqlite')) {
    throw new RuntimeException('Browser database isolation failed.');
}

if ($setup) {
    if (! $app->environment('testing') || file_exists($directory.'/browser.sqlite')) {
        throw new RuntimeException('Setup requires a new testing fixture.');
    }
    Artisan::call('migrate', ['--database' => 'sqlite', '--no-interaction' => true]);
    // Reuse verified seeder data in memory, then copy it to the disposable file.
    Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--no-interaction' => true]);
    Artisan::call('db:seed', ['--class' => ExplorerSeeder::class, '--no-interaction' => true]);
    $rows = [];
    foreach (['water_bodies', 'fish_species', 'water_body_fish'] as $table) {
        $query = DB::table($table);
        if ($table === 'water_bodies') {
            $query->whereIn('name', ['Sampaloc Lake', 'Pandin Lake', 'Yambo Lake']);
        }
        $rows[$table] = $query->get()->map(fn (object $row): array => (array) $row)->all();
    }
    touch($directory.'/browser.sqlite');
    config(['database.connections.sqlite.database' => $directory.'/browser.sqlite']);
    DB::purge('sqlite');
    Artisan::call('migrate', ['--database' => 'sqlite', '--no-interaction' => true]);
    foreach ($rows as $table => $records) {
        DB::table($table)->insert($records);
    }
    $password = getenv('AQUAMAP_TEST_ADMIN_PASSWORD');
    if (! is_string($password) || strlen($password) < 24) {
        throw new RuntimeException('An ephemeral test password is required.');
    }
    foreach ([true, false] as $isAdmin) {
        $user = new User;
        $user->name = $isAdmin ? 'Isolated administrator' : 'Isolated nonadministrator';
        $user->email = $isAdmin ? 'admin-fixture@example.test' : 'nonadmin-fixture@example.test';
        $user->password = $password;
        $user->is_admin = $isAdmin;
        $user->save();
    }
    file_put_contents($directory.'/photos/fixture-evidence.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6Z3UAAAAASUVORK5CYII='));
    for ($i = 0; $i < 18; $i++) {
        $report = new PollutionReport;
        $report->water_body_id = WaterBody::orderBy('id')->firstOrFail()->id;
        $report->pollution_type = 'solid_waste';
        $report->description = $i === 0 ? 'Synthetic browser review target. Never a real incident.' : 'Synthetic isolated browser fixture '.($i + 1);
        $report->status = [1 => 'verified', 2 => 'rejected', 3 => 'resolved'][$i] ?? 'pending';
        if ($i === 0) {
            $report->reporter_email = 'synthetic-reporter@example.test';
            $report->photo_path = 'fixture-evidence.png';
        }
        $report->save();
    }
    echo json_encode(['isolated' => true, 'lakes' => DB::table('water_bodies')->count(), 'targetLake' => WaterBody::orderBy('id')->value('id')]);
} elseif (PHP_SAPI === 'cli') {
    $reports = DB::table('pollution_reports')->get();
    echo json_encode([
        'reports' => $reports->count(),
        'pending' => $reports->where('status', 'pending')->count(),
        'photos' => count(glob($directory.'/photos/*') ?: []),
        'audits' => DB::table('report_reviews')->count(),
        'verified' => $reports->where('status', 'verified')->count(),
        'rejected' => $reports->where('status', 'rejected')->count(),
        'resolved' => $reports->where('status', 'resolved')->count(),
        'largestPhoto' => max([0, ...array_map('filesize', glob($directory.'/photos/*') ?: [])]),
    ]);
} else {
    $app->handleRequest(Request::capture());
}
