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
const finalValidation = process.argv.includes('--final');
const artifactDirectory = finalValidation ? 'final-validation/browser' : process.argv.includes('--research') ? 'research-audit/browser' : process.argv.includes('--fish') ? 'fish-names/browser' : process.argv.includes('--quick') ? 'redesign/browser/quick' : 'redesign/browser';
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
        assert(!result.exceptionDetails, result.exceptionDetails?.exception?.description ?? result.exceptionDetails?.text);
        return result.result.value;
    };
    const mapScale = () => evaluate(`(() => {
        const [a, b] = ['Balanac River', 'Yambo Lake'].map(title => [...document.querySelectorAll('.lake-marker')].find(node => node.title === title).getBoundingClientRect());
        return Math.hypot(a.x-b.x, a.y-b.y);
    })()`);
    const waitForZoomEnd = () => waitFor(() => evaluate('!document.querySelector(".leaflet-zoom-anim")'), 'Map zoom animation did not settle.');
    async function assertMetadataContrast(selector, state) {
        const ratio = await evaluate(String.raw`(() => {
            const text = document.querySelector(${JSON.stringify(selector)} + ' .water-row-meta');
            const channels = color => color.match(/[\d.]+/g).map(Number);
            const foreground = channels(getComputedStyle(text).color);
            let node = text;
            let background;
            while (node) {
                const color = channels(getComputedStyle(node).backgroundColor);
                if ((color[3] ?? 1) === 1) { background = color; break; }
                if (color[3] !== 0) throw new Error('Contrast check requires opaque backgrounds.');
                node = node.parentElement;
            }
            if (!background) throw new Error('Missing metadata background.');
            const luminance = color => color.slice(0, 3).map(value => {
                const channel = value / 255;
                return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
            }).reduce((sum, value, index) => sum + value * [0.2126, 0.7152, 0.0722][index], 0);
            const values = [luminance(foreground), luminance(background)].sort((a, b) => b - a);
            return (values[0] + 0.05) / (values[1] + 0.05);
        })()`);
        assert(ratio >= 4.5, `${state} metadata contrast ${ratio.toFixed(2)}:1 must meet 4.5:1.`);
        return ratio;
    }
    async function navigate(url) {
        const navigation = await send('Page.navigate', { url: base + url });
        assert(!navigation.errorText, navigation.errorText);
        await waitFor(async () => {
            const { frameTree } = await send('Page.getFrameTree');
            if (navigation.loaderId && frameTree.frame.loaderId !== navigation.loaderId) return false;
            return evaluate('document.readyState !== "loading"');
        }, 'New page did not finish navigation.');
    }
    let mobile = false;
    async function click(selector) {
        if (mobile && (selector.includes('data-lake-id') || selector.includes('data-category'))) {
            await evaluate('document.getElementById("water-sidebar").open = true');
            await pause(100);
        }
        const point = await evaluate(`(() => { const element = document.querySelector(${JSON.stringify(selector)}); if (!element) throw new Error('Missing control'); (element.closest('#lake-map') || element).scrollIntoView({block:'center'}); const r = element.getClientRects()[0] || element.getBoundingClientRect(); return {x:r.x+r.width/2,y:r.y+r.height/2}; })()`);
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
        await evaluate('document.querySelector(".explorer-map").scrollIntoView({block:"start"})');
        const metrics = fullPage ? await send('Page.getLayoutMetrics') : null;
        const shot = await send('Page.captureScreenshot', {
            format: 'png', captureBeyondViewport: fullPage,
            ...(fullPage ? { clip: { x: 0, y: 0, width: metrics.cssContentSize.width, height: metrics.cssContentSize.height, scale: 1 } } : {}),
        });
        await writeFile(path.join(output, name), Buffer.from(shot.data, 'base64'));
    }
    await send('Runtime.enable');
    await send('Page.enable');
    await send('DOM.enable');
    await send('CSS.enable');
    const requestedWidth = process.argv.find(argument => argument.startsWith('--width='))?.split('=')[1];
    const viewports = (process.argv.includes('--research') && !finalValidation ? [[1440, 1100], [390, 844]] : [[1440, 1100], [1024, 900], [390, 844], [320, 740]])
        .filter(([width]) => !requestedWidth || width === Number(requestedWidth));
    assert(viewports.length, 'Choose a supported viewport width.');
    for (const [width, height] of viewports) {
        let pinchVerified = false;
        mobile = width < 768;
        await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile });
        await send('Emulation.resetPageScaleFactor');
        await send('Emulation.setTouchEmulationEnabled', { enabled: mobile, maxTouchPoints: mobile ? 5 : 1 });
        await navigate('/map');
        await waitFor(() => evaluate('document.querySelectorAll(".lake-marker").length === 42'), 'Expected 42 sourced map locations and 12 unmapped rivers.');
        const lakes = await evaluate('JSON.parse(document.getElementById("lake-data").textContent)');
        assert.equal(lakes.length, 54);
        assert.equal(lakes.filter(item => item.category === 'river').length, 29);
        assert(lakes.every(item => !('verified_report_count' in item)));
        assert.equal(await evaluate('document.querySelectorAll("[data-profile-tab]").length'), 4);
        assert.equal(await evaluate('!!document.getElementById("map-selection")'), false);
        assert.equal(await evaluate('document.querySelectorAll(".report-fab").length'), 1);
        assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
        if (finalValidation) {
            assert.equal(await evaluate('[...document.querySelectorAll("h1,h2,h3")].some(node => /introduction/i.test(node.textContent))'), false);
            assert.equal(await evaluate('document.querySelector(".map-context > p").nextElementSibling.id'), 'research-notes');
            assert.equal(await evaluate('document.getElementById("water-search").labels.length'), 1);
            assert.equal(await evaluate('getComputedStyle(document.querySelector(".report-fab")).position'), 'fixed');
            for (const [category, count] of Object.entries({ all: 54, lake: 10, river: 29, waterfall: 13, spring: 2 })) {
                await click('[data-category="'+category+'"]');
                assert.equal(await evaluate('document.querySelectorAll("[data-water-row]:not([hidden])").length'), count);
                assert.equal(await evaluate('document.querySelector("[data-category='+category+'] [data-category-count]").textContent.trim()'), String(count));
            }
            await click('#reset-map');
        }
        if (!mobile) {
            const layout = await evaluate('(()=>{const s=document.getElementById("water-sidebar").getBoundingClientRect(),m=document.querySelector(".explorer-map").getBoundingClientRect(),l=document.getElementById("water-list");return {left:s.left,mapLeft:m.left,top:s.top,mapTop:m.top,ratio:s.width/(s.width+m.width),scroll:l.scrollHeight>l.clientHeight,overflow:getComputedStyle(l).overflowY};})()');
            assert(layout.left < layout.mapLeft);
            assert(Math.abs(layout.top-layout.mapTop) < 2);
            assert(layout.ratio >= .3 && layout.ratio <= .35);
            assert(layout.scroll && layout.overflow === 'auto');
            const initialPage = await evaluate('scrollY');
            await evaluate('document.getElementById("water-list").scrollTop = 400');
            assert.equal(await evaluate('scrollY'), initialPage);
        }
        const fishLakes = ['Pandin Lake', 'Sampaloc Lake', 'Yambo Lake'];
        const samples = lakes.filter(item => (!process.argv.includes('--quick') && [1440,390].includes(width)) || ['Balanac River','San Isidro River','Yambo Lake','Hidden Valley Springs','Pagsanjan Falls'].includes(item.name) || (process.argv.includes('--fish') && fishLakes.includes(item.name)) || (process.argv.includes('--research') && ['Al Fresco Springs Resort','Balon-Balon Falls','Bunot Lake','Calibato Lake'].includes(item.name)));
        const contrastSelector = '[data-lake-id="'+samples[0].id+'"]';
        const { root: documentNode } = await send('DOM.getDocument');
        const { nodeId: contrastNode } = await send('DOM.querySelector', { nodeId: documentNode.nodeId, selector: contrastSelector });
        const normalContrast = await assertMetadataContrast(contrastSelector, 'Normal');
        await send('CSS.forcePseudoState', { nodeId: contrastNode, forcedPseudoClasses: ['hover'] });
        const hoverContrast = await assertMetadataContrast(contrastSelector, 'Hovered');
        await send('CSS.forcePseudoState', { nodeId: contrastNode, forcedPseudoClasses: [] });
        for (const lake of samples) {
            await click('[data-lake-id="'+lake.id+'"]');
            if (lake.id === samples[0].id) {
                assert.equal(await evaluate(`document.querySelector(${JSON.stringify(contrastSelector)}).getAttribute('aria-pressed')`), 'true');
                const selectedContrast = await assertMetadataContrast(contrastSelector, 'Selected');
                results.push(`${width}px metadata contrast: normal ${normalContrast.toFixed(2)}:1, hovered ${hoverContrast.toFixed(2)}:1, selected ${selectedContrast.toFixed(2)}:1.`);
            }
            assert.equal(await evaluate('document.getElementById("selected-lake-heading").textContent'), lake.name);
            assert.equal(await evaluate('document.getElementById("water-detail-dialog").hidden'), false);
            assert.equal(await evaluate('document.getElementById("water-detail-dialog").getAttribute("aria-modal")'), 'false');
            assert.equal(await evaluate('getComputedStyle(document.documentElement).overflowY === "hidden"'), false);
            if (finalValidation) {
                const displayedType = await evaluate('document.querySelector("#selected-lake [data-water-type]").textContent.trim()');
                assert.equal(displayedType, { freshwater: 'Freshwater', brackish: 'Brackish Water', saltwater: 'Saltwater' }[lake.water_type] ?? 'Water type not yet verified', lake.name+': JSON/profile classification agreement.');
                assert.equal(await evaluate('[...document.querySelectorAll("#profile-tabs button, #close-water-detail, .report-fab, .leaflet-control-zoom a")].every(node => {const r=node.getBoundingClientRect();return r.width>=24 && r.height>=24 && !!(node.getAttribute("aria-label") || node.title || node.textContent.trim());})'), true, 'Primary controls need accessible names and AA minimum target sizes.');
            }
            const shape = lake.geography && lake.geography.geometry.type !== 'Point';
            assert.equal(await evaluate('document.querySelectorAll(".selected-water-course").length > 0'), !!shape);
            if (shape) {
                const paths=await evaluate('[...document.querySelectorAll(".selected-water-course")].map(p=>{const r=p.getBoundingClientRect(),m=document.getElementById("lake-map").getBoundingClientRect();return {color:p.getAttribute("stroke"),left:r.left-m.left,right:r.right-m.left,top:r.top-m.top,bottom:r.bottom-m.top,width:m.width,height:m.height};})');
                for(const path of paths) {
                    assert.equal(path.color,'#0066ff');
                    assert(path.left>=15&&path.top>=15&&path.right<=path.width-15&&path.bottom<=path.height-15,lake.name+': full selected extent must fit.');
                }
            }
            const overview=await evaluate('document.querySelector("#selected-lake [data-profile-section=overview]").textContent');
            if(lake.category==='river') {
                assert(overview.includes(shape?'Mapped course length:':'River course not yet mapped.'));
                assert(overview.includes('Whole-river length and complete headwater-to-mouth coverage are not independently verified.'));
            }
            if(lake.name==='Balanac River') {
                assert(overview.includes('19.272 km')&&overview.includes('10.70 km'));
                assert.equal(lake.geography.geometry.coordinates.length,20);
                assert.equal(await evaluate('document.querySelectorAll("#selected-lake [data-course-source]").length'),20);
            }
            if(lake.name==='Hidden Valley Springs')assert(overview.includes('spring source')||overview.includes('spring-source'));
            const expectedImage=await evaluate('document.querySelector("#selected-lake [data-water-image]")?.getAttribute("src") || null');
            if(expectedImage) {
                const photoResponse = await fetch(base+expectedImage);
                assert(photoResponse.ok,lake.name+': photo asset must load');
                await photoResponse.arrayBuffer();
            }
            if (process.argv.includes('--fish')) {
                await click('[data-profile-tab=fish]');
                const cards = await evaluate('document.querySelectorAll("#selected-lake [data-fish-card]").length');
                assert.equal(cards, fishLakes.includes(lake.name) ? 1 : 0, lake.name+': existing occurrence links only.');
                if (cards) {
                    const fish = await evaluate(`(() => {
                        const card = document.querySelector('#selected-lake [data-fish-card]');
                        const source = card.querySelector('[data-fish-name-source]');
                        return { english: card.querySelector('h4').textContent, scientific: card.querySelector('i').textContent,
                            filipino: card.querySelector('[data-filipino-name] [lang=fil]').textContent,
                            badge: card.querySelector('[data-evidence-badge]').textContent.trim(),
                            compatibility: card.querySelector('[data-compatibility-status]').dataset.compatibilityStatus,
                            source: source.href, rel: source.rel, overflow: card.scrollWidth > card.clientWidth };
                    })()`);
                    assert.equal(fish.english, 'Nile tilapia');
                    assert.equal(fish.scientific, 'Oreochromis niloticus');
                    assert.equal(fish.filipino, 'Plapla');
                    assert.equal(fish.badge, 'Historical aquaculture');
                    assert.equal(fish.compatibility, 'insufficient_data');
                    assert(fish.source.startsWith('https://repository.seafdec.org.ph/'));
                    assert(fish.rel.includes('noopener') && !fish.overflow);
                    const readable = await evaluate(`(() => {
                        const name = document.querySelector('#selected-lake [data-filipino-name]');
                        name.scrollIntoView({block:'center'});
                        const text = name.getBoundingClientRect(), panel = document.getElementById('lake-information').getBoundingClientRect();
                        const source = name.querySelector('a');
                        source.focus({preventScroll:true});
                        return text.top >= panel.top && text.bottom <= panel.bottom && document.activeElement === source;
                    })()`);
                    assert(readable, lake.name+': Filipino name must scroll into view and its source must accept keyboard focus.');
                    if (lake.name === 'Yambo Lake') await screenshot(width+'-fish-names.png');
                    await click('[data-profile-tab=research]');
                    const citations = await evaluate(`(() => {
                        const group = document.querySelector('#selected-lake [data-fish-name-evidence]').closest('details');
                        group.open = true;
                        return [...group.querySelectorAll('[data-fish-name-reference]')].map(link => ({url:link.href,text:link.closest('[data-research-card]').textContent}));
                    })()`);
                    assert.equal(citations.length, 2);
                    assert(citations.every(citation => citation.url.startsWith('https://') && citation.text.includes('SEAFDEC Aquaculture Department')));
                    assert(citations[0].text.includes('2001') && citations[1].text.includes('2019'));
                }
                await click('[data-profile-tab=overview]');
            }
            if (process.argv.includes('--research')) {
                await click('[data-profile-tab=research]');
                const library = await evaluate(String.raw`(() => {
                    const panel = document.querySelector('#selected-lake [data-profile-section=research]');
                    const urls = [...panel.querySelectorAll('[data-source-document]')].map(link => link.href.split('#')[0].replace(/\/$/, ''));
                    return { groups: panel.querySelectorAll('[data-research-group]').length, urls,
                        ids: [...panel.querySelectorAll('[data-research-source]')].map(source => source.id),
                        attribution: panel.textContent.includes('OpenStreetMap contributors, ODbL 1.0') };
                })()`);
                assert.equal(library.groups, 6);
                assert.equal(new Set(library.urls).size, library.urls.length, lake.name+': one citation per document.');
                assert(library.ids.every(id => id.endsWith('-selected')));
                if (lake.geography?.license_url) assert(library.attribution);
                if (lake.name === 'Yambo Lake') {
                    await evaluate('document.querySelector("#selected-lake [data-research-group=hydrology]").open=true');
                    await click('#selected-lake [data-research-group=hydrology] [data-citation-jump]');
                    assert.equal(await evaluate('document.activeElement.matches("[data-source-document]") && document.activeElement.closest("[data-research-card]").querySelector("details").open'), true);
                    assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
                    await screenshot(width+'-research.png');
                }
                await click('[data-profile-tab=overview]');
            }
            if(['Balanac River','San Isidro River','Yambo Lake'].includes(lake.name)) {
                const nav=await evaluate('(()=>{const n=document.getElementById("profile-tabs").getBoundingClientRect(),c=document.getElementById("lake-information").getBoundingClientRect(),f=document.querySelector(".report-fab").getBoundingClientRect();return {bottom:n.top>=c.bottom-1,overlap:n.left<f.right&&n.right>f.left&&n.top<f.bottom&&n.bottom>f.top};})()');
                assert(nav.bottom&&!nav.overlap,'Bottom tabs must remain accessible and clear of floating report action.');
                await screenshot(width+'-'+lake.name.split(' ')[0]+'.png');
                await click('.leaflet-control-zoom-in');
                assert.equal(await evaluate('document.getElementById("water-detail-dialog").hidden'),false);
                for(const tab of ['overview','classification','fish','research']) {
                    await click('[data-profile-tab="'+tab+'"]');
                    assert.equal(await evaluate('document.querySelector("#selected-lake [data-profile-section='+tab+']").hidden'),false);
                }
                await click('[data-profile-tab=classification]');
                const type=await evaluate('document.querySelector("#selected-lake [data-water-type]").textContent');
                assert.equal(type.trim(),lake.name==='Yambo Lake'?'Freshwater':'Water type not yet verified');
                await click('[data-profile-tab=overview]');
                assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'),true);
            }
            await click('#close-water-detail');
            assert.equal(await evaluate('document.querySelectorAll(".selected-water-course").length'),0);
        }
        await click('[data-category=river]');
        assert.equal(await evaluate('document.querySelectorAll("[data-water-row]:not([hidden])").length'),29);
        await click('#water-search');
        await send('Input.insertText', { text: 'Binan' });
        assert.equal(await evaluate('document.querySelectorAll("[data-water-row]:not([hidden])").length'),1);
        await evaluate('(()=>{const s=document.getElementById("water-search");s.value="no matching place fixture";s.dispatchEvent(new Event("input",{bubbles:true}));})()');
        assert.equal(await evaluate('document.getElementById("filter-empty").hidden'),false);
        await click('#reset-map');
        assert.equal(await evaluate('document.querySelectorAll("[data-water-row]:not([hidden])").length'),54);
        const river=lakes.find(item=>item.name==='Balanac River');
        await click('[data-lake-id="'+river.id+'"]');
        await evaluate('document.getElementById("lake-map").focus({preventScroll:true})');
        const beforeWheel=await evaluate('document.querySelector(".selected-water-course").getBoundingClientRect().width');
        const point=await evaluate('(()=>{const r=document.getElementById("lake-map").getBoundingClientRect();return {x:r.right-80,y:r.top+90};})()');
        await send('Input.dispatchMouseEvent',{type:'mouseWheel',...point,deltaX:0,deltaY:-240});
        await waitFor(()=>evaluate('document.querySelector(".selected-water-course").getBoundingClientRect().width > '+(beforeWheel+1)),'Focused wheel zoom must work with the profile open.');
        assert.equal(await evaluate('document.getElementById("water-detail-dialog").hidden'),false);
        if (finalValidation) {
            await pause(350);
            await waitForZoomEnd();
            const beforeSmallWheel = await mapScale();
            for (let step = 0; step < 8; step++) {
                await send('Input.dispatchMouseEvent', { type: 'mouseWheel', ...point, deltaX: 0, deltaY: -1.5 });
                await pause(10);
            }
            await waitFor(async () => await mapScale() > beforeSmallWheel+1, 'Small-delta wheel sequence must zoom with the profile open.');
            await pause(300);
            await waitForZoomEnd();
            if (mobile) {
                await click('[data-lake-id="'+river.id+'"]');
                await evaluate('document.querySelector(".explorer-map").scrollIntoView({block:"start"})');
                await pause(100);
                const center = await evaluate(`(() => {
                    const r = document.getElementById('lake-map').getBoundingClientRect();
                    for (const y of [r.top+60, r.top+100, r.top+140]) {
                        for (const x of [r.left+r.width/2, r.right-85, r.left+85]) {
                            if ([-25, 25].every(offset => {
                                const node = document.elementFromPoint(x+offset, y);
                                return node?.closest('#lake-map') && !node.closest('.leaflet-marker-icon, .leaflet-interactive, .leaflet-control');
                            })) return { x, y };
                        }
                    }
                    throw new Error('No unobstructed pinch start points.');
                })()`);
                const beforePinch = await mapScale();
                await evaluate(`window.finalTouchEvents = []; for (const type of ['touchstart', 'touchcancel']) document.addEventListener(type, event => window.finalTouchEvents.push({type, touches:event.touches.length, onMap:!!event.target.closest('#lake-map'), trusted:event.isTrusted}), {capture:true, passive:true});`);
                const fingers = distance => [{ x: center.x-distance, y: center.y, id: 1 }, { x: center.x+distance, y: center.y, id: 2 }];
                await send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: fingers(25) });
                for (let step = 1; step <= 8; step++) {
                    await send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: fingers(25+step*5) });
                    await pause(20);
                }
                await send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
                const touchEvents = await evaluate('window.finalTouchEvents');
                const starts = touchEvents.filter(event => event.type === 'touchstart');
                if (starts.some(event => event.touches === 2) && starts.every(event => event.onMap && event.trusted)) {
                    await waitFor(async () => await mapScale() > beforePinch+1, 'Two-finger CDP pinch must zoom after genuine map-target input.');
                    pinchVerified = true;
                } else {
                    results.push(`${width}px pinch NOT VERIFIED: simulated touch delivery missed the map despite clear DOM start points. Events: ${JSON.stringify(touchEvents)}. No application pinch conclusion from this attempt.`);
                }
                await pause(300);
                await waitForZoomEnd();
            }
            await click('[data-lake-id="'+river.id+'"]');
            await evaluate('document.querySelector(".explorer-map").scrollIntoView({block:"start"});document.getElementById("lake-map").focus({preventScroll:true})');
            const resizeZoomPoint = await evaluate('(()=>{const r=document.getElementById("lake-map").getBoundingClientRect();return {x:r.right-80,y:r.top+90};})()');
            await send('Input.dispatchMouseEvent', { type: 'mouseWheel', ...resizeZoomPoint, deltaX: 0, deltaY: -120 });
            await waitFor(() => evaluate('!!document.querySelector(".leaflet-zoom-anim")'), 'Expected an active zoom before testing concurrent layout resize.');
            for (const resizedWidth of [width + (mobile ? 40 : -80), width]) {
                await send('Emulation.setDeviceMetricsOverride', { width: resizedWidth, height, deviceScaleFactor: 1, mobile });
                try {
                    await waitFor(() => evaluate('(()=>{const m=document.getElementById("lake-map").getBoundingClientRect(),s=document.querySelector(".leaflet-overlay-pane svg"),p=document.querySelector(".selected-water-course").getBoundingClientRect();return Math.abs(Number(s.getAttribute("width"))-m.width*1.2)<4 && p.left>=m.left+15 && p.right<=m.right-15 && p.top>=m.top+15 && p.bottom<=m.bottom-15;})()'), 'Leaflet renderer and selected extent must follow live layout resizing.');
                } catch (error) {
                    const bounds = await evaluate('(()=>{const rect=selector=>document.querySelector(selector).getBoundingClientRect().toJSON();return {map:rect("#lake-map"),path:rect(".selected-water-course"),card:rect("#water-detail-dialog"),svg:document.querySelector(".leaflet-overlay-pane svg").getAttribute("width")};})()');
                    throw new Error(error.message+' '+JSON.stringify(bounds));
                }
                assert.equal(await evaluate('document.getElementById("selected-lake-heading").textContent'), river.name);
                assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
            }
            results.push(`${width}px: category counts, accessible control names/24px targets, JSON/profile types, small-delta CDP wheel and live Leaflet resize passed.${mobile && pinchVerified ? ' Two-finger CDP pinch passed.' : ''} Physical trackpad/touch hardware is not verified.`);
        }
        await click('#close-water-detail');
        await evaluate('document.querySelector(".report-fab").focus({preventScroll:true})');
        assert.equal(await evaluate('document.getElementById("lake-map").dispatchEvent(new WheelEvent("wheel",{deltaY:120,bubbles:true,cancelable:true}))'),true,'Unfocused map must allow normal page scrolling.');
        await click('#reset-map');
        if (finalValidation) {
            await evaluate('(()=>{const s=document.getElementById("water-search");s.value="Balanac River";s.dispatchEvent(new Event("input",{bubbles:true}));document.querySelector("#lake-map path[role=button]").focus();})()');
            await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
            await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
            assert.equal(await evaluate('document.getElementById("selected-lake-heading").textContent'), 'Balanac River');
            await click('#close-water-detail');
            await evaluate('(()=>{const s=document.getElementById("water-search");s.value="Hidden Valley Springs";s.dispatchEvent(new Event("input",{bubbles:true}));})()');
            await click('.lake-marker[title="Hidden Valley Springs"]');
            assert.equal(await evaluate('document.getElementById("selected-lake-heading").textContent'), 'Hidden Valley Springs');
            await click('#close-water-detail');
            await click('#reset-map');
        }
        await evaluate('(()=>{const s=document.getElementById("water-search");s.value="Pagsanjan Falls";s.dispatchEvent(new Event("input",{bubbles:true}));})()');
        await click('.lake-marker[title="Pagsanjan Falls"]');
        assert.equal(await evaluate('document.getElementById("selected-lake-heading").textContent'),'Pagsanjan Falls');
        await click('[data-profile-tab=overview]');
        await send('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowRight',code:'ArrowRight',windowsVirtualKeyCode:39});
        await send('Input.dispatchKeyEvent',{type:'keyUp',key:'ArrowRight',code:'ArrowRight',windowsVirtualKeyCode:39});
        assert.equal(await evaluate('document.activeElement.dataset.profileTab'),'classification');
        if (finalValidation) assert.equal(await evaluate('getComputedStyle(document.activeElement).outlineStyle !== "none" && parseFloat(getComputedStyle(document.activeElement).outlineWidth) >= 2'), true, 'Keyboard tab focus must be visible.');
        await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
        assert.equal(await evaluate('document.getElementById("water-detail-dialog").hidden'),true);
        results.push(width+'px: '+samples.length+' direct profiles, source geometry/photos/measurements, four bottom sections, privacy, independent list scrolling, filters/empty state, wheel zoom with open card, keyboard/Escape and fixed report action passed.'+(process.argv.includes('--fish') ? ' Three historical fish profiles: English/Filipino/scientific names, safe name sources, two Research citations and unassessed suitability passed; no fish assigned elsewhere.' : ''));
        await navigate('/reports/create');
        assert.equal(await evaluate('document.getElementById("photo").required'),true);
        const choices=await evaluate('JSON.parse(document.getElementById("incident-lakes").textContent)');
        assert.equal(choices.length,54);
        assert.equal(choices.find(item=>item.name==='San Isidro River').latitude,null);
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
