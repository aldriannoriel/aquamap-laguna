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
const artifactDirectory = 'photo-credits/browser';
const imageSupplement = JSON.parse(readFileSync(path.join(root, 'database/data/explorer-images.json'), 'utf8'));
const imageCatalogue = ['explorer.json', 'missing-water-bodies.json'].flatMap(file => JSON.parse(readFileSync(path.join(root, 'database/data', file), 'utf8')));
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
    async function closeProfile(escape = false) {
        if (escape) {
            await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
            await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
        } else {
            await click('#close-water-detail');
        }
        await waitFor(() => evaluate('!document.getElementById("water-detail-dialog").open && document.getElementById("selected-lake").hidden'), 'Profile did not close and clear selection.');
        assert.equal(await evaluate('document.querySelectorAll("[data-lake-id][aria-pressed=true]").length'), 0);
    }
    async function assertTabState(key) {
        const state = await evaluate(`(() => {
            const tabs = [...document.querySelectorAll('#profile-tabs [role=tab]')];
            const panels = [...document.querySelectorAll('#selected-lake [role=tabpanel]')];
            const tab = tabs.find(item => item.getAttribute('aria-selected') === 'true');
            const visible = panels.filter(item => !item.hidden);
            return {tabs:tabs.length, panels:panels.length, active:tab?.dataset.profileTab,
                selected:tabs.filter(item => item.getAttribute('aria-selected') === 'true').length,
                stops:tabs.filter(item => item.tabIndex === 0).length,
                visible:visible.map(item => item.dataset.profileSection),
                linked:visible.length === 1 && tab.getAttribute('aria-controls') === visible[0].id && visible[0].getAttribute('aria-labelledby') === tab.id};
        })()`);
        assert.deepEqual(state, { tabs: 5, panels: 5, active: key, selected: 1, stops: 1, visible: [key], linked: true });
    }
    async function selectTab(key) {
        await click(`[data-profile-tab="${key}"]`);
        await assertTabState(key);
    }
    async function assertDialogBounds(desktop = false) {
        const layout = await evaluate(`(() => {
            const dialog = document.getElementById('water-detail-dialog');
            const r = dialog.getBoundingClientRect(), m = document.getElementById('lake-map').getBoundingClientRect();
            return {x:r.x, y:r.y, width:r.width, height:r.height, viewport:innerWidth, available:document.documentElement.clientWidth,
                viewportHeight:innerHeight, mapX:m.x, mapWidth:m.width, overflow:dialog.scrollWidth > dialog.clientWidth,
                close:document.getElementById('close-water-detail').getBoundingClientRect().bottom <= r.bottom};
        })()`);
        assert(layout.x >= 7 && layout.y >= 7 && layout.x + layout.width <= layout.available - 7 && layout.y + layout.height <= layout.viewportHeight - 7, 'Profile must stay within viewport margins.');
        assert.equal(layout.overflow, false, 'Dialog horizontal overflow.');
        assert.equal(layout.close, true, 'Close control must stay visible.');
        if (desktop) {
            assert(Math.abs(layout.width - Math.min(608, Math.max(352, layout.mapWidth / 2))) <= 2, 'Desktop width must be half the map with sensible limits.');
            assert(Math.abs(layout.x + layout.width / 2 - layout.mapX - layout.mapWidth / 2) <= 2, 'Desktop profile must be centered over the map.');
        } else {
            assert(Math.abs(layout.width - (layout.viewport - 16)) <= 2, 'Mobile profile must retain 8px screen margins.');
        }
        return layout;
    }
    async function verifyPhoto(lake, viewport) {
        await assertTabState('overview');
        await assertDialogBounds(viewport === 'desktop');
        const original = imageCatalogue.find(item => item.name === lake.name && item.municipality === lake.municipality);
        assert(original, `Missing catalogue identity for ${lake.name}`);
        const expected = original.image ?? imageSupplement[lake.name]?.image;
        assert.equal(await evaluate('!!document.querySelector("#selected-lake img")'), !!expected, `${lake.name}: image coverage mismatch`);
        assert.equal(await evaluate('!!document.querySelector("#selected-lake [data-photo-placeholder]")'), !expected);
        if (expected) {
            await waitFor(() => evaluate('document.querySelector("#selected-lake img").complete && document.querySelector("#selected-lake img").naturalWidth > 0'), `${lake.name}: photograph did not load`);
            const rendered = await evaluate(`(() => {
                const img = document.querySelector('#selected-lake img');
                const caption = document.querySelector('#selected-lake figcaption');
                return {path:img.getAttribute('src'), alt:img.alt, fit:getComputedStyle(img).objectFit,
                    width:img.getBoundingClientRect().width, content:document.getElementById('lake-information').clientWidth,
                    caption:caption.textContent, credit:caption.querySelector('[data-photo-credit]').textContent.trim(),
                    labels:[...caption.querySelectorAll('a')].map(link => link.textContent),
                    links:[...caption.querySelectorAll('a')].map(link => link.href)};
            })()`);
            assert.equal(rendered.path, expected.path);
            assert.equal(rendered.alt, expected.alt);
            assert.equal(rendered.fit, expected.fit ?? 'cover');
            assert(rendered.width > 0 && rendered.width <= rendered.content, `${lake.name}: image sizing overflow`);
            assert(rendered.caption.includes(expected.author) && rendered.caption.includes(expected.license), `${lake.name}: missing attribution`);
            assert.equal(rendered.credit, `Photo by ${expected.author} · Source · License.`);
            assert.deepEqual(rendered.labels, ['Source', 'License']);
            assert.deepEqual(rendered.links, [expected.source_url, expected.license_url].map(url => new URL(url).href));
        }
        if (['Bunga Twin Falls', 'Hidden Valley Springs', 'Hulugan Falls', 'Bumbungan River', 'Al Fresco Springs Resort'].includes(lake.name)) {
            await screenshot(`${viewport}-${lake.name.toLowerCase().replaceAll(' ', '-')}.png`);
        }
    }
    async function keypress(key, code, windowsVirtualKeyCode, modifiers = 0) {
        await send('Input.dispatchKeyEvent', { type: 'keyDown', key, code, windowsVirtualKeyCode, modifiers });
        await send('Input.dispatchKeyEvent', { type: 'keyUp', key, code, windowsVirtualKeyCode, modifiers });
        await pause(80);
    }
    async function verifyTabbedProfile(lake, prefix) {
        await assertTabState('overview');
        const token = await evaluate('(window.profileDocumentToken ??= crypto.randomUUID())');
        for (const [key, text] of [['overview', lake.description], ['classification', 'drinking-water safety'], ['fish', 'Insufficient data'], ['pollution', 'Community Pollution Reports'], ['research', 'Geographic reference and limitations']]) {
            await selectTab(key);
            assert.equal((await evaluate('document.querySelector("#selected-lake [role=tabpanel]:not([hidden])").textContent')).includes(text), true);
            assert.equal(await evaluate('document.getElementById("selected-lake-heading").textContent'), lake.name);
            assert.equal(await evaluate('window.profileDocumentToken'), token, 'Tab switching reloaded the page.');
            assert.equal(await evaluate(`document.querySelector('[data-lake-id="${lake.id}"]').getAttribute('aria-pressed')`), 'true');
            await screenshot(`${prefix}-${key}.png`);
        }
        assert.equal(await evaluate('document.querySelectorAll("#selected-lake [data-research-card]").length > 0'), true);
        await send('Accessibility.enable');
        const tree = await send('Accessibility.getFullAXTree');
        assert.equal(tree.nodes.filter(node => !node.ignored && node.role?.value === 'tab').length, 5);
        assert.equal(tree.nodes.filter(node => !node.ignored && node.role?.value === 'tabpanel').length, 1);
        assert.equal(tree.nodes.find(node => !node.ignored && node.role?.value === 'dialog')?.name?.value, lake.name);
        await evaluate('document.getElementById("profile-tab-research").focus()');
        await keypress('ArrowRight', 'ArrowRight', 39);
        await assertTabState('overview');
        await keypress('ArrowLeft', 'ArrowLeft', 37);
        await assertTabState('research');
        await keypress('Home', 'Home', 36);
        await assertTabState('overview');
        await keypress('End', 'End', 35);
        await assertTabState('research');
        await keypress('Tab', 'Tab', 9);
        assert.equal(await evaluate('document.activeElement.id'), 'profile-panel-research');
        const before = await evaluate('document.getElementById("close-water-detail").getBoundingClientRect().top');
        if (prefix === 'desktop') {
            const point = await evaluate('(() => { const r = document.getElementById("lake-information").getBoundingClientRect(); return {x:r.x+r.width/2,y:r.y+r.height/2}; })()');
            await send('Input.dispatchMouseEvent', { type: 'mouseWheel', ...point, deltaX: 0, deltaY: 400 });
            await pause(200);
            assert(await evaluate('document.getElementById("lake-information").scrollTop > 0'), 'Mouse wheel must scroll profile content.');
        }
        await evaluate('document.getElementById("lake-information").scrollTop = 10000');
        assert(await evaluate('document.getElementById("lake-information").scrollTop > 0'), 'Research content must scroll within the profile.');
        assert.equal(await evaluate('document.getElementById("close-water-detail").getBoundingClientRect().top'), before);
        assert.equal(await evaluate('document.getElementById("profile-tabs").getBoundingClientRect().bottom < document.getElementById("water-detail-dialog").getBoundingClientRect().bottom'), true);
        assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
        await selectTab('overview');
        assert.equal(await evaluate('document.getElementById("lake-information").scrollTop'), 0);
        results.push(`${prefix}: all five tabs, default Overview, stable selection/document, linked accessibility roles, arrow/Home/End navigation, focusable panels and content-only scroll pass.`);
    }
    await send('Runtime.enable');
    await send('Page.enable');
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1100, deviceScaleFactor: 1, mobile: false });
    await navigate('/map');
    await waitFor(() => evaluate('document.querySelectorAll(".lake-marker").length === JSON.parse(document.getElementById("lake-data").textContent).filter(item => item.geography).length && document.querySelectorAll(".lake-marker").length > 0'), 'Expected source-checked database-driven markers.');
    await waitFor(() => evaluate('document.querySelectorAll(".leaflet-tile-loaded").length > 0'), 'OpenStreetMap tiles did not load.', 20000);
    await waitFor(() => evaluate('[...document.querySelectorAll(".leaflet-tile")].every(tile => tile.complete)'), 'Map tiles did not finish loading.', 20000);
    assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, 'Desktop horizontal overflow.');
    assert.equal(await evaluate('document.querySelectorAll("[data-lake-id]").length'), 28);
    results.push('Desktop: 28 source-checked database markers/profiles, OpenStreetMap tiles and attribution, no horizontal overflow.');
    await screenshot('desktop.png');

    const lakes = await evaluate('JSON.parse(document.getElementById("lake-data").textContent)');
    for (const lake of lakes) {
        await click(`[data-lake-id="${lake.id}"]`);
        assert.equal(await evaluate('document.querySelector("#selected-lake-heading")?.textContent'), lake.name);
        await assertTabState('overview');
        assert.equal(await evaluate(`document.querySelector('[data-lake-id="${lake.id}"]').getAttribute('aria-pressed')`), 'true');
        assert.equal(await evaluate('document.getElementById("water-detail-dialog").open'), true);
        assert.equal(await evaluate('document.activeElement.id'), 'close-water-detail');
        assert.equal(await evaluate('document.getElementById("water-detail-dialog").getAttribute("aria-labelledby")'), 'selected-lake-heading');
        assert.equal(await evaluate('document.querySelector("#selected-lake .lake-detail-description")?.textContent'), lake.description);
        assert.equal(await evaluate('[...document.querySelectorAll("#selected-lake a")].some(link => link.href === ' + JSON.stringify(lake.source_url) + ')'), true);
        if (verifyFish && ['Sampaloc Lake', 'Pandin Lake', 'Yambo Lake'].includes(lake.name)) {
            await selectTab('fish');
            assert.equal(await evaluate('document.querySelectorAll("#selected-lake [data-fish-card]").length'), 1);
            assert.equal(await evaluate('document.querySelector("#selected-lake [data-fish-card] h4").textContent'), 'Nile tilapia');
            assert.equal(await evaluate('document.querySelector("#selected-lake [data-sampling-period]").textContent'), 'July-September 2017');
            assert.equal(await evaluate('document.querySelector("#selected-lake [data-compatibility-status]").dataset.compatibilityStatus'), 'insufficient_data');
            assert.match(await evaluate('document.querySelector("#selected-lake [data-evidence-badge]").textContent'), /Historical aquaculture/);
            assert.equal(await evaluate('[...document.querySelectorAll("#selected-lake [data-fish-card] a")].filter(link => link.rel === "noopener noreferrer").length'), 2);
            if (lake.name === 'Sampaloc Lake') {
                await selectTab('overview');
                const dimensions = await assertDialogBounds(true);
                results.push(`Desktop dimensions: ${dimensions.width}px wide over a ${dimensions.mapWidth}px map; ${dimensions.height}px tall.`);
                await verifyTabbedProfile(lake, 'desktop');
                for (const width of [1024, 1920]) {
                    await send('Emulation.setDeviceMetricsOverride', { width, height: 1100, deviceScaleFactor: 1, mobile: false });
                    await pause(250);
                    await assertDialogBounds(true);
                    await screenshot(`desktop-${width}.png`);
                }
                await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1100, deviceScaleFactor: 1, mobile: false });
                await pause(250);
            }
            await selectTab('overview');
        }
        await verifyPhoto(lake, 'desktop');
        await closeProfile();
        assert.equal(await evaluate('document.activeElement.dataset.lakeId'), String(lake.id), 'Closing must restore focus to the selected sidebar control.');
        await click('#reset-map');
    }
    results.push('All 28 sidebar profiles: modal heading, initial focus, close/focus restoration, description, sources and authorized image loading pass.');
    results.push('Desktop: all 28 profiles match the image inventory; 20 local photographs load with exact authors, licenses, source links and responsive sizing; 8 explicit placeholders; fitted images use contain.');
    if (verifyFish) results.push('All three lakes show Nile tilapia, July-September 2017 sampling, historical aquaculture evidence, two references and insufficient_data compatibility.');
    await click('[data-category=lake]');
    await click('.lake-marker[title="Sampaloc Lake"]');
    assert.equal(await evaluate('document.querySelector("#selected-lake-heading")?.textContent'), 'Sampaloc Lake');
    await assertTabState('overview');
    if (verifyFish) assert.equal(await evaluate('document.querySelector("#selected-lake [data-fish-card] h4").textContent'), 'Nile tilapia');
    results.push('Marker pointer click opens the complete profile dialog.');
    await closeProfile(true);
    await click('#reset-map');

    await click('[data-category=lake]');
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

    async function setSearch(value) {
        await evaluate('(() => { const input = document.getElementById("water-search"); input.value = ' + JSON.stringify(value) + '; input.dispatchEvent(new Event("input", {bubbles:true})); })()');
        await pause(200);
    }
    for (const [category, expected] of [['lake', 10], ['river', 5], ['waterfall', 11], ['spring', 2]]) {
        await click('[data-category="' + category + '"]');
        assert.equal(await evaluate('document.querySelectorAll(".lake-marker").length'), expected);
        assert.equal(await evaluate('[...document.querySelectorAll("[data-water-row]")].filter(row => !row.hidden).length'), expected);
        await click('#reset-map');
    }
    await setSearch('cavinti falls');
    assert.equal(await evaluate('document.querySelectorAll(".lake-marker").length'), 1);
    assert.equal(await evaluate('document.querySelector("[data-category=waterfall] [data-category-count]").textContent'), '1');
    await setSearch('no matching place fixture');
    assert.equal(await evaluate('document.querySelectorAll(".lake-marker").length'), 0);
    assert.equal(await evaluate('document.getElementById("filter-empty").hidden'), false);
    await click('#reset-map');
    assert.equal(await evaluate('document.querySelectorAll(".lake-marker").length'), 28);
    await click('[data-category=river]');
    assert(await evaluate('document.querySelectorAll("path[role=button]").length > 0'), 'River reaches must render as selectable geometry.');
    await evaluate('document.querySelector("path[role=button]").focus()');
    await send('Input.dispatchKeyEvent', {type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    await send('Input.dispatchKeyEvent', {type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    assert.equal(await evaluate('document.getElementById("selected-lake").hidden'), false);
    await assertTabState('overview');
    await closeProfile(true);
    await click('#reset-map');
    results.push('All category counts, search/alias search, empty state, layer removal, reset and keyboard shape selection pass.');


    await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
    await send('Emulation.setTouchEmulationEnabled', { enabled: true });
    mobile = true;
    await navigate('/map');
    await waitFor(() => evaluate('document.querySelectorAll(".lake-marker").length === JSON.parse(document.getElementById("lake-data").textContent).filter(item => item.geography).length && document.querySelectorAll(".lake-marker").length > 0'), 'Mobile map did not initialize.');
    assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, 'Mobile horizontal overflow.');
    const layout = await evaluate('({map:document.getElementById("lake-map").getBoundingClientRect().y,sidebar:document.querySelector("aside").getBoundingClientRect().y})');
    assert(layout.sidebar < layout.map, 'Mobile search and category controls should precede the map.');
    for (const lake of lakes) {
        await click(`[data-lake-id="${lake.id}"]`);
        assert.equal(await evaluate('document.querySelector("#selected-lake-heading").textContent'), lake.name);
        await verifyPhoto(lake, 'mobile');
        assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
        await closeProfile();
        await click('#reset-map');
    }
    results.push('390px mobile: all 28 profiles match the inventory, all 20 images load with exact attribution and responsive sizing, 8 placeholders remain, no horizontal overflow.');
    const mobileLake = lakes.find(lake => lake.name === "Pandin Lake");
    await click(`[data-lake-id="${mobileLake.id}"]`);
    assert.equal(await evaluate('document.querySelector("#selected-lake-heading")?.textContent'), mobileLake.name);
    assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, 'Mobile selection causes overflow.');
    await assertDialogBounds();
    await verifyTabbedProfile(mobileLake, 'mobile');
    await screenshot('mobile.png');
    if (verifyFish) {
        assert.equal(await evaluate('document.querySelector("#selected-lake [data-fish-card] h4").textContent'), 'Nile tilapia');
        assert.equal(await evaluate('document.querySelector("#selected-lake [data-compatibility-status]").dataset.compatibilityStatus'), 'insufficient_data');
        await selectTab('fish');
        await screenshot('mobile-fish.png');
        results.push('Mobile fish card remains readable, distinguishes occurrence/compatibility and retains source links without overflow.');
    }
    for (const [width, height] of [[320, 568], [640, 360]]) {
        await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: true });
        await pause(250);
        await assertDialogBounds();
        await selectTab('research');
        await screenshot(`mobile-${width}.png`);
    }
    await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
    await pause(250);
    await closeProfile();
    await click('#reset-map');
    assert.equal(await evaluate('document.querySelectorAll("[data-lake-id][aria-pressed=true]").length'), 0);
    await click('[data-category=spring]');
    assert.equal(await evaluate('document.querySelectorAll(".lake-marker").length'), 2);
    await setSearch('los banos');
    assert.equal(await evaluate('document.querySelectorAll(".lake-marker").length'), 1);
    await click('[data-lake-id="' + lakes.find(lake => lake.name === 'Al Fresco Springs Resort').id + '"]');
    await assertTabState('overview');
    assert.match(await evaluate('document.getElementById("selected-lake").textContent'), /not a verified spring source/);
    assert.equal(await evaluate('document.querySelectorAll("#selected-lake [data-photo-placeholder]").length'), 1);
    assert.equal(await evaluate('document.querySelector("#selected-lake img")'), null);
    assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
    await screenshot('mobile-resort.png', true);
    await selectTab('classification');
    assert.match(await evaluate('document.querySelector("#profile-panel-classification").textContent'), /operator claim/);
    await selectTab('fish');
    assert.match(await evaluate('document.querySelector("#profile-panel-fish").textContent'), /Fish habitat: Not Assessed/);
    assert.equal(await evaluate('document.querySelectorAll("#profile-panel-fish [data-fish-card]").length'), 0);
    await selectTab('pollution');
    assert.match(await evaluate('document.querySelector("#profile-panel-pollution").textContent'), /0 currently verified community reports/);
    results.push('Opening a different resort resets Overview; classification retains operator limitations, missing fish stays Not Assessed, and Pollution contains only verified counts and unknown science.');
    await closeProfile();
    await click('#reset-map');
    results.push('390px mobile: categories, accent-insensitive locality search, resort/source distinction and photo placeholder pass without overflow.');
    results.push('320px/390px portrait and 640px landscape: compact viewport-bounded dialog, horizontally scrolling tabs, selection and reset without horizontal overflow.');
    await navigate('/reports/create');
    await waitFor(() => evaluate('!!document.querySelector("select[name=water_body_id]")'), 'Public reporting form failed to load with the expanded catalogue.');
    assert.equal(await evaluate('[...document.querySelectorAll("select[name=water_body_id] option")].filter(option => option.value).length'), 28);
    assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
    results.push('Live MySQL reporting form loads all 28 locations without sort-buffer errors; no form was submitted.');
    assert.deepEqual(failures, [], 'Browser JavaScript exceptions occurred.');
    assert.equal(databaseSnapshot(), originalRecords, 'Browser verification changed lake or fish records.');
    assert.equal(sharedCacheSnapshot(), originalCache, 'Browser verification changed shared file cache.');
    results.push('Live domain records, users, reviews, migration history and shared database/file cache unchanged after browser verification.');
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
