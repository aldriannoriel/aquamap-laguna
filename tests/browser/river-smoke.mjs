import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { once } from 'node:events';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import net from 'node:net';
import path from 'node:path';

// Uses an installed browser and an isolated profile; no browser packages or downloads.
const root = process.cwd();
function databaseSnapshot() {
    const check = spawnSync('php', [], {
        cwd: root, windowsHide: true, encoding: 'utf8',
        input: `<?php
try {
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    $connection = Illuminate\\Support\\Facades\\DB::connection();
    if ($connection->getDriverName() !== 'mysql' || $connection->getDatabaseName() !== 'aquamap_laguna') { exit(1); }
    if ($connection->selectOne('SELECT DATABASE() AS name', [], false)->name !== 'aquamap_laguna') { exit(1); }
    $snapshot = [App\\Models\\WaterBody::orderBy('id')->get()->toArray(), App\\Models\\FishSpecies::orderBy('id')->get()->toArray(), App\\Models\\WaterBodyFish::orderBy('id')->get()->toArray()];
    foreach (['pollution_reports', 'users', 'report_reviews', 'migrations', 'cache', 'cache_locks'] as $table) {
        $snapshot[$table] = $connection->table($table)->orderBy(in_array($table, ['cache', 'cache_locks']) ? 'key' : 'id')->get()->toArray();
    }
    echo hash('sha256', json_encode($snapshot));
} catch (Throwable $exception) { exit(1); }
`,
    });
    assert.equal(check.status, 0, 'Browser checks require the verified aquamap_laguna MySQL database.');
    assert.match(check.stdout, /^[a-f0-9]{64}$/);
    return check.stdout;
}
const originalRecords = databaseSnapshot();
function sharedCacheSnapshot() {
    const directory = path.join(root, 'storage/framework/cache');
    return JSON.stringify(readdirSync(directory, { recursive: true, withFileTypes: true }).filter(file => file.isFile())
        .map(file => { const name = path.join(file.parentPath, file.name); return [path.relative(directory, name), createHash('sha256').update(readFileSync(name)).digest('hex')]; })
        .sort((a, b) => a[0].localeCompare(b[0])));
}
const originalCache = sharedCacheSnapshot();
const browserPath = process.env.AQUAMAP_BROWSER || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
assert(existsSync(browserPath), 'Set AQUAMAP_BROWSER to an installed Chromium browser.');
const artifactDirectory = 'river-expansion/browser';
const imageSupplement = JSON.parse(readFileSync(path.join(root, 'database/data/explorer-images.json'), 'utf8'));
const imageCatalogue = ['explorer.json', 'missing-water-bodies.json', 'waterfall-expansion.json'].flatMap(file => JSON.parse(readFileSync(path.join(root, 'database/data', file), 'utf8')));
const imageReplacements = JSON.parse(readFileSync(path.join(root, 'database/data/waterfall-photos.json'), 'utf8'));
const output = path.join(root, 'storage/app', artifactDirectory);
const profile = path.resolve(output, `profile-${Date.now()}`);
assert(profile.startsWith(`${path.resolve(root, 'storage/app', artifactDirectory)}${path.sep}`));
await mkdir(output, { recursive: true });
const fixtureDirectory = path.resolve(root, 'storage/app/day4-browser', `fixture-${Date.now()}`);
assert(fixtureDirectory.startsWith(`${path.resolve(root, 'storage/app/day4-browser')}${path.sep}`));
for (const directory of ['', 'sessions', 'cache', 'photos']) {
    await mkdir(path.join(fixtureDirectory, directory), { recursive: true });
}
const fixtureEnvironment = { ...process.env, APP_ENV: 'testing', AQUAMAP_REPORT_FIXTURE: fixtureDirectory, AQUAMAP_BROWSER_FULL_CATALOGUE: '1', AQUAMAP_BROWSER_RIVERS: '1' };
const fixtureRouter = path.join(root, 'tests/browser/report-fixture.php');
const setup = spawnSync('php', [fixtureRouter, 'setup'], { cwd: root, env: fixtureEnvironment, windowsHide: true, encoding: 'utf8' });
assert.equal(setup.status, 0, 'Isolated map fixture setup failed.');
assert.equal(JSON.parse(setup.stdout).lakes, 54);
const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function unusedPort() {
    const server = net.createServer();
    server.listen(0, '127.0.0.1');
    await once(server, 'listening');
    const port = server.address().port;
    await new Promise((resolve) => server.close(resolve));
    return port;
}

async function waitFor(task, message, timeout = 15000) {
    const until = Date.now() + timeout;
    while (Date.now() < until) {
        try { if (await task()) return; } catch { /* Server/page may still be starting. */ }
        await pause(150);
    }
    throw new Error(message);
}

const httpPort = await unusedPort();
const debugPort = await unusedPort();
const base = `http://127.0.0.1:${httpPort}`;
const server = spawn('php', ['-S', `127.0.0.1:${httpPort}`, '-t', path.join(root, 'public'), fixtureRouter], {
    cwd: root, env: fixtureEnvironment, windowsHide: true, stdio: 'ignore',
});
let browser;
let socket;
let send;
const failures = [];
const results = [];
try {
    await waitFor(async () => (await fetch(`${base}/up`)).ok, 'Local Laravel server did not start.');
    browser = spawn(browserPath, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', `--remote-debugging-port=${debugPort}`, `--user-data-dir=${profile}`, 'about:blank'], {
        windowsHide: true, stdio: 'ignore',
    });
    await waitFor(async () => (await fetch(`http://127.0.0.1:${debugPort}/json/list`)).ok, 'Installed browser did not expose its isolated test session.');
    const tabs = await (await fetch(`http://127.0.0.1:${debugPort}/json/list`)).json();
    const tab = tabs.find((item) => item.type === 'page' && item.url === 'about:blank');
    assert(tab, 'Expected a fresh test tab.');
    socket = new WebSocket(tab.webSocketDebuggerUrl);
    await once(socket, 'open');
    const pending = new Map();
    let nextId = 0;
    socket.addEventListener('message', ({ data }) => {
        const message = JSON.parse(data);
        if (message.id && pending.has(message.id)) {
            const { resolve, reject, timer } = pending.get(message.id);
            clearTimeout(timer);
            pending.delete(message.id);
            if (message.error) reject(new Error(message.error.message));
            else resolve(message.result);
        }
        if (message.method === 'Runtime.exceptionThrown') failures.push(message.params.exceptionDetails.text);
    });
    send = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++nextId;
        const timer = setTimeout(() => { pending.delete(id); reject(new Error(`Timed out: ${method}`)); }, 15000);
        pending.set(id, { resolve, reject, timer });
        socket.send(JSON.stringify({ id, method, params }));
    });
    const evaluate = async (expression) => {
        const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        assert(!result.exceptionDetails, result.exceptionDetails?.text);
        return result.result.value;
    };
    async function navigate(url) {
        const navigation = await send('Page.navigate', { url: base + url });
        assert(!navigation.errorText, navigation.errorText);
        await waitFor(async () => {
            const { frameTree } = await send('Page.getFrameTree');
            if (navigation.loaderId && frameTree.frame.loaderId !== navigation.loaderId) return false;
            return evaluate('document.readyState === "complete"');
        }, 'New page did not finish navigation.');
    }
    let mobile = false;
    async function click(selector) {
        const point = await evaluate(`(() => { const element = document.querySelector(${JSON.stringify(selector)}); if (!element) throw new Error('Missing control'); (element.closest('#lake-map') || element).scrollIntoView({block:'center'}); const r = element.getBoundingClientRect(); return {x:r.x+r.width/2,y:r.y+r.height/2}; })()`);
        await pause(100);
        if (mobile) {
            await send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [point] });
            await send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        } else {
            await send('Input.dispatchMouseEvent', { type: 'mousePressed', ...point, button: 'left', clickCount: 1 });
            await send('Input.dispatchMouseEvent', { type: 'mouseReleased', ...point, button: 'left', clickCount: 1 });
        }
        await pause(80);
    }
    async function screenshot(name, fullPage = false) {
        await evaluate('document.getElementById("map-selection").scrollIntoView({block:"start"})');
        const metrics = fullPage ? await send('Page.getLayoutMetrics') : null;
        const shot = await send('Page.captureScreenshot', {
            format: 'png', captureBeyondViewport: fullPage,
            ...(fullPage ? { clip: { x: 0, y: 0, width: metrics.cssContentSize.width, height: metrics.cssContentSize.height, scale: 1 } } : {}),
        });
        await writeFile(path.join(output, name), Buffer.from(shot.data, 'base64'));
    }
    await send('Runtime.enable');
    await send('Page.enable');
    for (const [width, height] of [[1440, 1100], [390, 844], [320, 740]]) {
        mobile = width < 1024;
        await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile });
        await send('Emulation.setTouchEmulationEnabled', { enabled: mobile });
        await navigate('/map');
        await waitFor(() => evaluate('document.querySelectorAll(".lake-marker").length === 40'), 'Expected 40 verified locations; unmapped rivers must have no marker.');
        const lakes = await evaluate('JSON.parse(document.getElementById("lake-data").textContent)');
        assert.equal(lakes.length, 52);
        assert.equal(lakes.filter(item => item.category === 'river').length, 29);
        assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
        const selectedPaths = 'document.querySelectorAll(".selected-water-course")';
        for (const lake of lakes.filter(item => (width !== 320 && !process.argv.includes('--quick')) || ['Balanac River', 'San Isidro River', 'Yambo Lake'].includes(item.name))) {
            await click(`[data-lake-id="${lake.id}"]`);
            assert.equal(await evaluate('document.getElementById("map-selection-heading").textContent'), lake.name);
            assert.equal(await evaluate('document.getElementById("water-detail-dialog").open'), false, 'Selection card must allow map interactions.');
            const shape = lake.geography && lake.geography.geometry.type !== 'Point';
            assert.equal(await evaluate(`${selectedPaths}.length > 0`), !!shape);
            if (shape) {
                const paths = await evaluate(`[...${selectedPaths}].map(path => ({color:path.getAttribute('stroke'),weight:Number(path.getAttribute('stroke-width')),rect: (()=>{const r=path.getBoundingClientRect(),m=document.getElementById('lake-map').getBoundingClientRect();return {left:r.left-m.left,right:r.right-m.left,top:r.top-m.top,bottom:r.bottom-m.top,width:m.width,height:m.height};})()}))`);
                for (const path of paths) {
                    assert.equal(path.color, '#0066ff');
                    assert(path.weight >= 4);
                    assert(path.rect.left >= 20 && path.rect.top >= 20 && path.rect.right <= path.rect.width - 20 && path.rect.bottom <= path.rect.height - 20, `${lake.name}: selected extent must fit inside the map.`);
                }
            }
            const text = await evaluate('document.getElementById("map-selection-summary").textContent');
            if (lake.category === 'river') {
                assert(text.includes(shape ? 'Mapped segment length:' : 'River course not yet mapped.'));
                assert(text.includes(['Bumbungan River', 'San Pedro River', 'Tunasan River'].includes(lake.name) ? 'Length not yet verified.' : 'Reported river length:'));
            }
            if (lake.category === 'lake') {
                assert(!text.includes('river length'));
                assert(text.includes(['Yambo Lake', 'Pandin Lake', 'Calibato Lake'].includes(lake.name) ? 'Reported surface area:' : 'Surface area not yet verified.'));
            }
            if (['Balanac River', 'Yambo Lake', 'San Isidro River'].includes(lake.name)) {
                await screenshot(`${width}-${lake.name.split(' ')[0]}.png`);
                await click('.leaflet-control-zoom-in');
                assert.equal(await evaluate('document.getElementById("water-detail-dialog").open'), false);
                await click('#open-water-profile');
                assert.equal(await evaluate('document.getElementById("water-detail-dialog").open'), true);
                for (const tab of ['overview', 'classification', 'fish', 'pollution', 'research']) {
                    await click(`[data-profile-tab="${tab}"]`);
                    assert.equal(await evaluate(`document.querySelector('#selected-lake [data-profile-section="${tab}"]').hidden`), false);
                }
                assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
                await click('#close-water-detail');
                await waitFor(() => evaluate('document.getElementById("map-selection").hidden'), 'Closing profile must clear selection.');
            } else {
                await click('#clear-water-selection');
            }
            assert.equal(await evaluate(`${selectedPaths}.length`), 0);
            await click('#reset-map');
        }
        results.push(`${width}px: river and lake selections show source lengths/areas separately, correct blue geometry, fitBounds, unmapped fallbacks, clear, tabs and an interactive map without overflow.`);
        await click('[data-category=river]');
        assert.equal(await evaluate('document.querySelector("[data-category=river] [data-category-count]").textContent'), '29');
        await evaluate('(()=>{const search=document.getElementById("water-search");search.value="Binan";search.dispatchEvent(new Event("input",{bubbles:true}));})()');
        assert.equal(await evaluate('document.querySelectorAll("[data-water-row]:not([hidden])").length'), 1);
        await click('#reset-map');
        const river = lakes.find(lake => lake.name === 'Balanac River');
        await click(`[data-lake-id="${river.id}"]`);
        const nextRiver = lakes.find(lake => lake.name === 'Santa Cruz River');
        await click(`[data-lake-id="${nextRiver.id}"]`);
        await click(`.lake-marker[title="Santa Cruz River"]`);
        assert.equal(await evaluate('document.getElementById("map-selection-heading").textContent'), 'Santa Cruz River');
        await evaluate('document.querySelector(".selected-water-course").focus()');
        await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
        await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
        assert.equal(await evaluate('document.getElementById("map-selection-heading").textContent'), 'Santa Cruz River');
        await click('#clear-water-selection');
        await navigate('/reports/create');
        const choices = await evaluate('JSON.parse(document.getElementById("incident-lakes").textContent)');
        assert.equal(choices.length, 54);
        assert.equal(choices.find(item => item.name === 'San Isidro River').latitude, null);
        await evaluate("(()=>{const category=document.getElementById('water_body_category');category.value='river';category.dispatchEvent(new Event('change',{bubbles:true}));})()");
        assert.equal(await evaluate('[...document.querySelectorAll("#water_body_id option")].filter(option=>option.value).length'), 29);
        results.push(`${width}px: 29 river reporting choices include unmapped profiles; no incident point is fabricated.`);
    }
    assert.deepEqual(failures, []);
    assert.equal(databaseSnapshot(), originalRecords, 'Real database must remain unchanged.');
    assert.equal(sharedCacheSnapshot(), originalCache, 'Shared cache must remain unchanged.');
    await writeFile(path.join(output, 'results.json'), JSON.stringify({ passed: true, results, javascriptExceptions: failures }, null, 2));
    console.log(JSON.stringify({ passed: true, results, artifacts: `storage/app/${artifactDirectory}` }, null, 2));
} catch (error) {
    await writeFile(path.join(output, 'results.json'), JSON.stringify({ passed: false, results, error: error.message }, null, 2));
    if (send && socket?.readyState === WebSocket.OPEN) {
        const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
        await writeFile(path.join(output, 'failure.png'), Buffer.from(shot.data, 'base64'));
    }
    throw error;
} finally {
    if (send && socket?.readyState === WebSocket.OPEN) {
        try { await send('Browser.close'); } catch { /* Browser closes the socket during shutdown. */ }
        socket.close();
    }
    if (browser && browser.exitCode === null) browser.kill();
    server.kill();
    await pause(300);
    await rm(profile, { recursive: true, force: true, maxRetries: 6, retryDelay: 200 });
    await rm(fixtureDirectory, { recursive: true, force: true, maxRetries: 6, retryDelay: 200 });
}
