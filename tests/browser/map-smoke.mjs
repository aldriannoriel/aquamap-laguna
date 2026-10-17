import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { once } from 'node:events';
import { existsSync } from 'node:fs';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import net from 'node:net';
import path from 'node:path';

// Uses an installed browser and an isolated profile; no browser packages or downloads.
const root = process.cwd();
const verifyFish = process.argv.includes('--fish');
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
    echo hash('sha256', json_encode([App\\Models\\WaterBody::orderBy('id')->get()->toArray(), App\\Models\\FishSpecies::orderBy('id')->get()->toArray(), App\\Models\\WaterBodyFish::orderBy('id')->get()->toArray()]));
} catch (Throwable $exception) { exit(1); }
`,
    });
    assert.equal(check.status, 0, 'Browser checks require the verified aquamap_laguna MySQL database.');
    assert.match(check.stdout, /^[a-f0-9]{64}$/);
    return check.stdout;
}
const originalRecords = databaseSnapshot();
const browserPath = process.env.AQUAMAP_BROWSER || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
assert(existsSync(browserPath), 'Set AQUAMAP_BROWSER to an installed Chromium browser.');
const artifactDirectory = verifyFish ? 'day3-browser' : 'day2-browser';
const output = path.join(root, 'storage/app', artifactDirectory);
const profile = path.resolve(output, `profile-${Date.now()}`);
assert(profile.startsWith(`${path.resolve(root, 'storage/app', artifactDirectory)}${path.sep}`));
await mkdir(output, { recursive: true });
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
const server = spawn('php', ['-S', `127.0.0.1:${httpPort}`, '-t', path.join(root, 'public'), path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {
    cwd: path.join(root, 'public'), windowsHide: true, stdio: 'ignore',
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
    let mobile = false;
    async function click(selector) {
        const point = await evaluate(`(() => { const element = document.querySelector(${JSON.stringify(selector)}); if (!element) throw new Error('Missing control'); element.scrollIntoView({block:'center'}); const r = element.getBoundingClientRect(); return {x:r.x+r.width/2,y:r.y+r.height/2}; })()`);
        if (mobile) {
            await send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [point] });
            await send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        } else {
            await send('Input.dispatchMouseEvent', { type: 'mousePressed', ...point, button: 'left', clickCount: 1 });
            await send('Input.dispatchMouseEvent', { type: 'mouseReleased', ...point, button: 'left', clickCount: 1 });
        }
        await pause(400);
    }
    async function screenshot(name, fullPage = false) {
        await evaluate('window.scrollTo(0, 0)');
        const metrics = fullPage ? await send('Page.getLayoutMetrics') : null;
        const shot = await send('Page.captureScreenshot', {
            format: 'png', captureBeyondViewport: fullPage,
            ...(fullPage ? { clip: { x: 0, y: 0, width: metrics.cssContentSize.width, height: metrics.cssContentSize.height, scale: 1 } } : {}),
        });
        await writeFile(path.join(output, name), Buffer.from(shot.data, 'base64'));
    }
    await send('Runtime.enable');
    await send('Page.enable');
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1100, deviceScaleFactor: 1, mobile: false });
    await send('Page.navigate', { url: `${base}/map` });
    await waitFor(() => evaluate('document.querySelectorAll(".lake-marker").length === 3'), 'Expected three database-driven markers.');
    await waitFor(() => evaluate('document.querySelectorAll(".leaflet-tile-loaded").length > 0'), 'OpenStreetMap tiles did not load.', 20000);
    await waitFor(() => evaluate('[...document.querySelectorAll(".leaflet-tile")].every(tile => tile.complete)'), 'Map tiles did not finish loading.', 20000);
    assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, 'Desktop horizontal overflow.');
    assert.equal(await evaluate('document.querySelectorAll("[data-lake-id]").length'), 3);
    results.push('Desktop: three live database markers/cards, OpenStreetMap tiles and attribution, no horizontal overflow.');
    await screenshot('desktop.png');

    const lakes = await evaluate('JSON.parse(document.getElementById("lake-data").textContent)');
    for (const lake of lakes) {
        await click(`[data-lake-id="${lake.id}"]`);
        assert.equal(await evaluate('document.querySelector("#selected-lake h2")?.textContent'), lake.name);
        assert.equal(await evaluate(`document.querySelector('[data-lake-id="${lake.id}"]').getAttribute('aria-pressed')`), 'true');
        assert.equal(await evaluate('document.querySelector(".leaflet-popup .lake-detail-title")?.textContent'), lake.name);
        assert.equal(await evaluate('document.querySelector("#selected-lake .lake-detail-description")?.textContent'), lake.description);
        assert.equal(await evaluate('document.querySelector("#selected-lake .lake-detail-source")?.href'), lake.source_url);
        if (verifyFish) {
            assert.equal(await evaluate('document.querySelectorAll("#selected-lake [data-fish-card]").length'), 1);
            assert.equal(await evaluate('document.querySelector("#selected-lake [data-fish-card] h4").textContent'), 'Nile tilapia');
            assert.equal(await evaluate('document.querySelector("#selected-lake [data-sampling-period]").textContent'), 'July-September 2017');
            assert.equal(await evaluate('document.querySelector("#selected-lake [data-compatibility-status]").dataset.compatibilityStatus'), 'insufficient_data');
            assert.match(await evaluate('document.querySelector("#selected-lake [data-evidence-badge]").textContent'), /Historical aquaculture/);
            assert.equal(await evaluate('[...document.querySelectorAll("#selected-lake [data-fish-card] a")].filter(link => link.rel === "noopener noreferrer").length'), 2);
            if (lake.id === lakes[0].id) await screenshot('desktop-fish.png', true);
        }
        await click('#reset-map');
    }
    results.push('All three sidebar selections: correct popup, selected state, description and safe research link; reset clears selection.');
    if (verifyFish) results.push('All three lakes show Nile tilapia, July-September 2017 sampling, historical aquaculture evidence, two references and insufficient_data compatibility.');
    await click('.lake-marker[title="Sampaloc Lake"]');
    assert.equal(await evaluate('document.querySelector("#selected-lake h2")?.textContent'), 'Sampaloc Lake');
    if (verifyFish) assert.equal(await evaluate('document.querySelector("#selected-lake [data-fish-card] h4").textContent'), 'Nile tilapia');
    results.push('Marker pointer click updates the sidebar and lake detail panel.');
    await click('#reset-map');

    const scale = await evaluate('document.querySelector(".leaflet-control-scale-line").textContent');
    await click('.leaflet-control-zoom-in');
    assert.notEqual(await evaluate('document.querySelector(".leaflet-control-scale-line").textContent'), scale);
    await click('.leaflet-control-zoom-out');
    const beforePan = await evaluate('document.querySelector(".leaflet-map-pane").style.transform');
    await evaluate('document.getElementById("lake-map").focus()');
    await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'ArrowRight', code: 'ArrowRight', windowsVirtualKeyCode: 39 });
    await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'ArrowRight', code: 'ArrowRight', windowsVirtualKeyCode: 39 });
    await pause(400);
    assert.notEqual(await evaluate('document.querySelector(".leaflet-map-pane").style.transform'), beforePan);
    await click('#reset-map');
    results.push('Zoom in/out, keyboard panning and reset work.');

    await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
    await send('Emulation.setTouchEmulationEnabled', { enabled: true });
    mobile = true;
    await send('Page.reload');
    await waitFor(() => evaluate('document.querySelectorAll(".lake-marker").length === 3'), 'Mobile map did not initialize.');
    assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, 'Mobile horizontal overflow.');
    const layout = await evaluate('({map:document.getElementById("lake-map").getBoundingClientRect().y,sidebar:document.querySelector("aside").getBoundingClientRect().y})');
    assert(layout.map < layout.sidebar, 'Mobile map should precede the sidebar.');
    await click(`[data-lake-id="${lakes[0].id}"]`);
    assert.equal(await evaluate('document.querySelector("#selected-lake h2")?.textContent'), lakes[0].name);
    assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, 'Mobile selection causes overflow.');
    const popupLayout = await evaluate('({popup:document.querySelector(".leaflet-popup").getBoundingClientRect().top,map:document.getElementById("lake-map").getBoundingClientRect().top})');
    assert(popupLayout.popup >= popupLayout.map, 'Mobile popup title must remain inside the map.');
    await screenshot('mobile.png');
    if (verifyFish) {
        assert.equal(await evaluate('document.querySelector("#selected-lake [data-fish-card] h4").textContent'), 'Nile tilapia');
        assert.equal(await evaluate('document.querySelector("#selected-lake [data-compatibility-status]").dataset.compatibilityStatus'), 'insufficient_data');
        await screenshot('mobile-fish.png', true);
        results.push('Mobile fish card remains readable, distinguishes occurrence/compatibility and retains source links without overflow.');
    }
    await click('#reset-map');
    assert.equal(await evaluate('document.querySelectorAll("[aria-pressed=true]").length'), 0);
    results.push('390px mobile: map-first stacked layout, selection, popup and reset work without horizontal overflow.');
    assert.deepEqual(failures, [], 'Browser JavaScript exceptions occurred.');
    assert.equal(databaseSnapshot(), originalRecords, 'Browser verification changed lake or fish records.');
    results.push('Live water body, species and association records unchanged after browser verification.');
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
}
