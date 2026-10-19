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
const verifySpam = process.argv.includes('--spam');

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
    echo hash('sha256', json_encode([App\\Models\\WaterBody::orderBy('id')->get()->toArray(), App\\Models\\FishSpecies::orderBy('id')->get()->toArray(), App\\Models\\WaterBodyFish::orderBy('id')->get()->toArray(), Illuminate\\Support\\Facades\\DB::table('pollution_reports')->orderBy('id')->get()->toArray(), Illuminate\\Support\\Facades\\DB::table('users')->orderBy('id')->get()->toArray(), Illuminate\\Support\\Facades\\DB::table('report_reviews')->orderBy('id')->get()->toArray()]));
} catch (Throwable $exception) { exit(1); }
`,
    });
    assert.equal(check.status, 0, 'Browser checks require the verified aquamap_laguna MySQL database.');
    assert.match(check.stdout, /^[a-f0-9]{64}$/);
    return check.stdout;
}
const originalRecords = databaseSnapshot();
function sharedCacheSnapshot() {
    const directory=path.join(root,'storage/framework/cache');
    const hashes=readdirSync(directory,{recursive:true,withFileTypes:true}).filter(file=>file.isFile())
        .map(file=>{const name=path.join(file.parentPath,file.name);return [path.relative(directory,name),createHash('sha256').update(readFileSync(name)).digest('hex')];})
        .sort((a,b)=>a[0].localeCompare(b[0]));
    return JSON.stringify(hashes);
}
const originalCache=sharedCacheSnapshot();
const browserPath = process.env.AQUAMAP_BROWSER || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
assert(existsSync(browserPath), 'Set AQUAMAP_BROWSER to an installed Chromium browser.');
const artifactDirectory = 'day4-browser';
const output = path.join(root, 'storage/app', artifactDirectory);
const resultFile = path.join(output, verifySpam ? 'spam-results.json' : 'results.json');
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
const fixture = path.resolve(output, 'fixture-'+Date.now());
assert(fixture.startsWith(path.resolve(root, 'storage/app/day4-browser')+path.sep));
await mkdir(fixture, {recursive:true});
for (const folder of ['sessions','photos','cache']) await mkdir(path.join(fixture,folder));
const environment = {...process.env, AQUAMAP_REPORT_FIXTURE:fixture,
 APP_ENV:'local', APP_DEBUG:'false', DB_CONNECTION:'sqlite', DB_DATABASE:path.join(fixture,'browser.sqlite'), DB_URL:'',
 APP_KEY:'base64:'+Buffer.from(crypto.getRandomValues(new Uint8Array(32))).toString('base64'),
 APP_CONFIG_CACHE:path.join(fixture,'unused-config.php'), APP_ROUTES_CACHE:path.join(fixture,'unused-routes.php'),
 CACHE_STORE:'file', SESSION_DRIVER:'file'};
function fixtureCommand(mode) {
 const result=spawnSync('php',['tests/browser/report-fixture.php',mode], {cwd:root, windowsHide:true,encoding:'utf8',env:{...environment,APP_ENV:mode==='setup'?'testing':'local'}});
 assert.equal(result.status,0, 'Isolated browser fixture failed: '+result.stderr);
 try {return JSON.parse(result.stdout);} catch {throw new Error('Fixture returned non-JSON output: '+result.stdout.slice(0,300));}
}
assert.deepEqual(fixtureCommand('setup'), {isolated:true,lakes:3});
const server=spawn('php',['-d','upload_max_filesize=5M','-d','post_max_size=8M','-S','127.0.0.1:'+httpPort,'-t',path.join(root,'public'),path.join(root,'tests/browser/report-fixture.php')],{cwd:root,env:environment,windowsHide:true,stdio:'ignore'});
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

    const rejectedCsrf = await fetch(`${base}/reports`, {method:'POST', body:new URLSearchParams({description:'Isolated missing-token fixture'})});
    assert.equal(rejectedCsrf.status,419,'Missing CSRF token must be rejected without an accepted origin.');
    results.push('Laravel CSRF protection rejects a tokenless POST; real browser forms use the session token.');
    async function navigate() {
        await send('Page.navigate',{url:`${base}/reports/create`});
        await waitFor(()=>evaluate('!!document.querySelector("#incident-map .leaflet-control-zoom-in")'),'Incident map did not initialize.');
        await waitFor(()=>evaluate('document.querySelectorAll("#incident-map .leaflet-tile-loaded").length>0'),'Incident map tiles did not load.',20000);
        await pause(3100); // Exercise the real server timer; never disable protection in browser fixtures.
    }
    async function field(id, value) {
        if (id === 'water_body_id') {
            await evaluate(`(() => {
                const lake = JSON.parse(document.getElementById('incident-lakes').textContent).find(item => String(item.id) === ${JSON.stringify(value)});
                const category = document.getElementById('water_body_category');
                if (lake && category.value !== lake.category) { category.value = lake.category; category.dispatchEvent(new Event('change', { bubbles: true })); }
            })()`);
        }
        await evaluate(`(()=>{const e=document.getElementById(${JSON.stringify(id)});e.value=${JSON.stringify(value)};e.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    }
    async function choosePhoto() {
        const document = await send('DOM.getDocument');
        const {nodeId} = await send('DOM.querySelector',{nodeId:document.root.nodeId,selector:'#photo'});
        await send('DOM.setFileInputFiles',{nodeId,files:[path.join(fixture,'synthetic-photo.png')]});
        assert.equal(await evaluate('document.getElementById("photo").files[0].size'),3*1024*1024);
    }
    if (verifySpam) {
        await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:`${base}/reports/create`});
        await waitFor(()=>evaluate('!!document.getElementById("report-form")'),'Reporting form did not load.');
        // Fetch and immediately submit a fresh server-issued token without waiting for tiles/assets.
        const early=await evaluate(`(async()=>{
            const html=await(await fetch('/reports/create')).text();
            const page=new DOMParser().parseFromString(html,'text/html');
            const lakes=JSON.parse(page.getElementById('incident-lakes').textContent);
            const body=new URLSearchParams({_token:page.querySelector('[name=_token]').value,report_form_token:page.querySelector('[name=report_form_token]').value,water_body_id:String(lakes[0].id),pollution_type:'solid_waste',description:'Synthetic early-submission fixture.'});
            const response=await fetch('/reports',{method:'POST',headers:{Accept:'application/json'},body});
            const data=await response.json();return{status:response.status,fields:Object.keys(data.errors||{})};
        })()`);
        assert.equal(early.status,422);assert(early.fields.includes('report_form_token'));
        await navigate();
        const lakes=await evaluate('JSON.parse(document.getElementById("incident-lakes").textContent)');
        assert.equal(await evaluate('document.getElementById("website").getClientRects().length'),0,'Honeypot must be visually hidden.');
        assert.equal(await evaluate('document.getElementById("website").tabIndex'),-1,'Honeypot must not enter keyboard navigation.');
        await field('water_body_id',String(lakes[0].id));await field('pollution_type','solid_waste');
        await field('description','Synthetic private spam-check observation.');await field('website','https://bot.example.test');
        await click('#submit-report');
        await waitFor(()=>evaluate('!!document.getElementById("form-errors")'),'Honeypot submission was not rejected.');
        assert.equal(await evaluate('document.getElementById("description").value'),'Synthetic private spam-check observation.');
        assert.equal(await evaluate('document.getElementById("website").value'),'');
        assert.equal(fixtureCommand('check').reports,0);
        await waitFor(()=>evaluate('!!document.querySelector("#incident-map .leaflet-control-zoom-in")'),'Retry form did not initialize.');
        await click('#submit-report');
        await waitFor(()=>evaluate('!!document.getElementById("report-success")'),'Legitimate retry did not succeed.');
        assert.equal(await evaluate('!!document.getElementById("duplicate-warning")'),false);
        await navigate();
        await field('water_body_id',String(lakes[0].id));await field('pollution_type','solid_waste');
        await field('description','A different synthetic observation for the same lake and type.');
        await click('#submit-report');
        await waitFor(()=>evaluate('!!document.getElementById("duplicate-warning")'),'Same-session repeat warning was not shown.');
        assert.equal(await evaluate('document.getElementById("duplicate-warning").textContent.includes("still been saved privately")'),true);
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
        await screenshot('spam-desktop-warning.png',true);
        results.push('Desktop: premature POST rejected server-side, hidden/nonfocusable honeypot rejects a filled bot field, safe inputs survive, legitimate retry succeeds, and a recent same-session lake/type repeat is saved pending with a private warning.');
        await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
        await send('Emulation.setTouchEmulationEnabled',{enabled:true});mobile=true;
        await navigate();
        assert.equal(await evaluate('document.getElementById("website").getClientRects().length'),0);
        await field('water_body_id',String(lakes[0].id));await field('pollution_type','solid_waste');
        await field('description','Synthetic mobile repeat; not a real pollution report.');
        await click('#submit-report');
        await waitFor(()=>evaluate('!!document.getElementById("duplicate-warning")'),'Mobile repeat warning did not appear.');
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Mobile warning overflow.');
        await screenshot('spam-mobile-warning.png');
        assert.deepEqual(fixtureCommand('check'),{reports:3,pending:3,photos:0,largestPhoto:0});
        const limited=await evaluate('fetch("/reports",{method:"POST",body:new FormData(document.getElementById("report-form"))}).then(r=>r.status)');
        assert.equal(limited,429,'The original five-attempts/hour limit must remain active.');
        results.push('390px mobile: accessible blank honeypot, normal anonymous submission and readable nonblocking repeat warning; all three reports remain private/pending in SQLite. Sixth POST receives 429 after two rejected attempts and three valid submissions.');
    } else {
        // A valid PNG with inert padding tests transport above this machine's default 2 MB limit.
        const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6Z3UAAAAASUVORK5CYII=','base64');
        await writeFile(path.join(fixture,'synthetic-photo.png'),Buffer.concat([png,Buffer.alloc(3*1024*1024-png.length)]));
        await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
        await navigate();
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
        await screenshot('desktop-form.png',true);
        await click('#submit-report');
        assert.equal(await evaluate('document.getElementById("water_body_category").validity.valueMissing'),true,'Category must be chosen first.');
        await field('water_body_category','lake');
        await click('#submit-report');
        assert.equal(await evaluate('document.getElementById("water_body_id").validity.valueMissing'),true,'Native required validation must be present.');
        await evaluate('document.getElementById("report-form").noValidate=true');
        await click('#submit-report');
        await waitFor(()=>evaluate('!!document.getElementById("form-errors")'),'Server validation summary did not appear.');
        assert.match(await evaluate('document.getElementById("form-errors").textContent'),/Please choose a water body/);
        await waitFor(()=>evaluate('document.activeElement.id==="form-errors"'),'Validation summary did not receive focus.');
        await screenshot('desktop-errors.png');
        const lakes=await evaluate('JSON.parse(document.getElementById("incident-lakes").textContent)');
        await field('water_body_id',String(lakes[0].id));
        assert.equal(await evaluate('document.getElementById("latitude").value'),'','Choosing a lake must not assign an incident point.');
        await field('pollution_type','solid_waste');
        await field('description','Synthetic desktop browser fixture. Not an actual pollution observation.');
        await field('reporter_email','synthetic@example.test');
        await click('#use-map-center');
        assert.equal(await evaluate('document.querySelectorAll(".incident-point").length'),1);
        await click('#clear-incident');
        assert.equal(await evaluate('document.getElementById("latitude").value'),'');
        await click('#incident-map .leaflet-control-zoom-in');
        // Click away from the lake reference circle and map controls.
        const p=await evaluate('(()=>{const e=document.getElementById("incident-map");e.scrollIntoView({block:"center"});const r=e.getBoundingClientRect();return{x:r.x+r.width*.65,y:r.y+r.height*.65}})()');
        await send('Input.dispatchMouseEvent',{type:'mousePressed',...p,button:'left',clickCount:1});
        await send('Input.dispatchMouseEvent',{type:'mouseReleased',...p,button:'left',clickCount:1});
        assert.equal(await evaluate('document.querySelectorAll(".incident-point").length'),1);
        const selectedPoint=await evaluate('document.getElementById("latitude").value');
        await field('water_body_id',String(lakes[1].id));
        assert.equal(await evaluate('document.getElementById("latitude").value'),selectedPoint,'Lake selection must preserve a deliberately selected incident point.');
        await choosePhoto();
        await click('#submit-report');
        await waitFor(()=>evaluate('!!document.getElementById("report-success")'),'Desktop submission did not succeed.');
        assert.equal(await evaluate('document.getElementById("description").value'),'');
        assert.equal(await evaluate('document.body.textContent.includes("synthetic@example.test")'),false);
        await screenshot('desktop-success.png');
        results.push('Desktop: required/server errors, focused error summary, lake centering without automatic incident coordinates, zoom/click/clear, preserved point across lake changes, private 3 MB photo submission and generic pending confirmation.');

        await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
        await send('Emulation.setTouchEmulationEnabled',{enabled:true});
        mobile=true;
        await navigate();
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Mobile form overflow.');
        await screenshot('mobile-form.png',true);
        await field('water_body_id',String(lakes[2].id));
        await field('pollution_type','other');
        await field('description','Synthetic mobile browser fixture. Not a real pollution report.');
        await field('reporter_email','invalid-email');
        await click('#use-map-center');
        await evaluate('document.getElementById("report-form").noValidate=true');
        await choosePhoto();
        await click('#submit-report');
        await waitFor(()=>evaluate('!!document.getElementById("form-errors")'),'Mobile server error did not appear.');
        assert.equal(await evaluate('document.getElementById("description").value'),'Synthetic mobile browser fixture. Not a real pollution report.');
        await waitFor(()=>evaluate('document.querySelectorAll(".incident-point").length===1'),'Selected point was not restored after validation.');
        assert.equal(await evaluate('(()=>{const p=document.querySelector(".incident-point").getBoundingClientRect();const m=document.getElementById("incident-map").getBoundingClientRect();return p.left>=m.left&&p.right<=m.right&&p.top>=m.top&&p.bottom<=m.bottom})()'),true,'Restored incident point must be visible in the map.');
        assert.equal(await evaluate('document.getElementById("photo").files.length'),0,'Photo must be reselected after validation.');
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
        await screenshot('mobile-errors.png');
        await field('reporter_email','');
        await click('#clear-incident');
        const tap=await evaluate('(()=>{const e=document.getElementById("incident-map");e.scrollIntoView({block:"center"});const r=e.getBoundingClientRect();return{x:r.x+r.width*.7,y:r.y+r.height*.65}})()');
        await send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[tap]});
        await send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
        await waitFor(()=>evaluate('document.querySelectorAll(".incident-point").length===1'),'Mobile map tap did not select a point.');
        await choosePhoto();
        await click('#submit-report');
        await waitFor(()=>evaluate('!!document.getElementById("report-success")'),'Mobile submission did not succeed.');
        await screenshot('mobile-success.png');
        const saved=fixtureCommand('check');
        assert.deepEqual(saved,{reports:2,pending:2,photos:2,largestPhoto:3*1024*1024});
        results.push('390px mobile: responsive form, touch map selection/clear, server email errors retain text/point, explicit photo reselection, successful anonymous submission. Two reports/photos exist only in isolated SQLite/storage, both pending.');
    }
    assert.deepEqual(failures, [], 'Browser JavaScript exceptions occurred.');
    assert.equal(databaseSnapshot(), originalRecords, 'Browser verification changed lake or fish records.');
    assert.equal(sharedCacheSnapshot(),originalCache,'Browser fixture changed the shared application cache.');
    results.push('Real MySQL lake, fish, association and pollution report records and shared application cache unchanged.');
    await writeFile(resultFile, JSON.stringify({ passed: true, results, javascriptExceptions: failures }, null, 2));
    console.log(JSON.stringify({ passed: true, results, artifacts: `storage/app/${artifactDirectory}` }, null, 2));
} catch (error) {
    await writeFile(resultFile, JSON.stringify({ passed: false, results, error: error.message }, null, 2));
    if (send && socket?.readyState === WebSocket.OPEN) {
        const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
        await writeFile(path.join(output, verifySpam ? 'spam-failure.png' : 'failure.png'), Buffer.from(shot.data, 'base64'));
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
    assert(fixture.startsWith(path.resolve(root,'storage/app/day4-browser')+path.sep));
    await rm(fixture,{recursive:true,force:true,maxRetries:6,retryDelay:200});
}
