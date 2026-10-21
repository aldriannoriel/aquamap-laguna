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
    echo hash('sha256', json_encode([App\\Models\\WaterBody::orderBy('id')->get()->toArray(), App\\Models\\FishSpecies::orderBy('id')->get()->toArray(), App\\Models\\WaterBodyFish::orderBy('id')->get()->toArray(), Illuminate\\Support\\Facades\\DB::table('pollution_reports')->orderBy('id')->get()->toArray(), Illuminate\\Support\\Facades\\DB::table('users')->orderBy('id')->get()->toArray(), Illuminate\\Support\\Facades\\DB::table('report_reviews')->orderBy('id')->get()->toArray(), ...collect(['agencies','agency_water_body','agency_directory_initializations','referrals','referral_events','jobs','failed_jobs','migrations'])->map(fn($table) => Illuminate\\Support\\Facades\\DB::table($table)->get()->toArray())->all()]));
} catch (Throwable $exception) { exit(1); }
`,
    });
    assert.equal(check.status, 0, 'Browser checks require the verified aquamap_laguna MySQL database.');
    assert.match(check.stdout, /^[a-f0-9]{64}$/);
    return check.stdout;
}
const originalRecords = databaseSnapshot();
const originalEnvironment = createHash('sha256').update(readFileSync('.env')).digest('hex');
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
const artifactDirectory = process.argv.includes('--baseline') ? 'map-tiles/baseline' : 'map-tiles/browser';
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
const fixture = path.resolve(root, 'storage/app/day5-browser', 'fixture-'+Date.now());
assert(fixture.startsWith(path.resolve(root, 'storage/app/day5-browser')+path.sep));
await mkdir(fixture, {recursive:true});
for (const folder of ['sessions','photos','cache']) await mkdir(path.join(fixture,folder));
const testPassword=Buffer.from(crypto.getRandomValues(new Uint8Array(24))).toString('hex')+'aA1!';
const environment = {...process.env, AQUAMAP_REPORT_FIXTURE:fixture, AQUAMAP_BROWSER_REFERRALS:'1',
 APP_ENV:'local', APP_DEBUG:'false', DB_CONNECTION:'sqlite', DB_DATABASE:path.join(fixture,'browser.sqlite'), DB_URL:'',
 APP_KEY:'base64:'+Buffer.from(crypto.getRandomValues(new Uint8Array(32))).toString('base64'),
 APP_CONFIG_CACHE:path.join(fixture,'unused-config.php'), APP_ROUTES_CACHE:path.join(fixture,'unused-routes.php'),
 CACHE_STORE:'file', SESSION_DRIVER:'file'};
function fixtureCommand(mode) {
 const result=spawnSync('php',['tests/browser/admin-fixture.php',mode], {cwd:root, windowsHide:true,encoding:'utf8',env:{...environment,APP_ENV:mode==='setup'?'testing':'local',...(mode==='setup'?{AQUAMAP_TEST_ADMIN_PASSWORD:testPassword}:{})}});
 assert.equal(result.status,0, 'Isolated browser fixture failed: '+result.stderr);
 try {return JSON.parse(result.stdout);} catch {throw new Error('Fixture returned non-JSON output: '+result.stdout.slice(0,300));}
}
const fixtureInfo=fixtureCommand('setup');
assert.equal(fixtureInfo.isolated,true);assert.equal(fixtureInfo.lakes,3);
const server=spawn('php',['-d','upload_max_filesize=5M','-d','post_max_size=8M','-S','127.0.0.1:'+httpPort,'-t',path.join(root,'public'),path.join(root,'tests/browser/admin-fixture.php')],{cwd:root,env:environment,windowsHide:true,stdio:'ignore'});
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
        const point = await evaluate(`(async () => { const element = [...document.querySelectorAll(${JSON.stringify(selector)})].find(item => item.getClientRects().length > 0); if (!element) throw new Error('Missing visible control'); element.scrollIntoView({block:'center'}); await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); const r = element.getBoundingClientRect(); return {x:r.x+r.width/2,y:r.y+r.height/2}; })()`);
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


    async function navigate(url, condition) {
        const navigation=await send('Page.navigate',{url:base+url});
        await waitFor(async()=>{
            const {frameTree}=await send('Page.getFrameTree');
            if(navigation.loaderId && frameTree.frame.loaderId!==navigation.loaderId) return false;
            return evaluate(`document.readyState==='complete' && (${condition})`);
        },'Administrator page did not become ready.');
    }
    async function field(id,value) {
        await evaluate(`(()=>{const e=document.getElementById(${JSON.stringify(id)});e.value=${JSON.stringify(value)};e.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    }
    async function login(email) {
        await field('email',email);
        await field('password',testPassword);
        await click('#admin-sign-in');
    }
    // New location controls: no GPS access on load; denied/unavailable fallback; explicit GPS confirmation.
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
    const baseline = process.argv.includes('--baseline');
    const tileRequests = new Map();
    let phase = '';
    socket.addEventListener('message', ({data}) => {
        const message = JSON.parse(data), p = message.params;
        if (message.method === 'Network.requestWillBeSent' && p.request.url.startsWith('https://tile.openstreetmap.org/')) {
            const headers = p.request.headers;
            tileRequests.set(p.requestId, {phase, url:p.request.url, referer:headers.Referer ?? headers.referer ?? null});
        }
        if (message.method === 'Network.requestWillBeSentExtraInfo' && tileRequests.has(p.requestId)) {
            const headers = p.headers;
            Object.assign(tileRequests.get(p.requestId), {referer:headers.Referer ?? headers.referer ?? null,
                requestCacheControl:headers['Cache-Control'] ?? headers['cache-control'] ?? null});
        }
        if (message.method === 'Network.responseReceived' && tileRequests.has(p.requestId)) {
            Object.assign(tileRequests.get(p.requestId), {status:p.response.status, mimeType:p.response.mimeType,
                cacheControl:p.response.headers['cache-control'] ?? p.response.headers['Cache-Control'] ?? null});
        }
    });
    await send('Network.enable');
    await navigate('/admin/login','!!document.getElementById("admin-sign-in")');
    await login('admin-fixture@example.test');
    await waitFor(()=>evaluate('document.querySelector("[aria-label=\\"Administrator navigation\\"]")!==null'),'Sign-in incomplete.');
    for (const width of process.argv.includes('--simulated-only') ? [] : baseline ? [1440] : [1440,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width===390});
        await send('Emulation.setTouchEmulationEnabled',{enabled:width===390,maxTouchPoints:1});
        mobile=width===390;
        for (const [name,url,id] of baseline ? [['admin','/admin/reports/1','private-report-map']] : [
            ['admin','/admin/reports/1','private-report-map'],['report','/reports/create','incident-map'],['public','/map','lake-map']]) {
            phase = name+'-'+width;
            await navigate(url,'document.querySelector("#'+id+' .leaflet-tile")!==null');
            await waitFor(()=>evaluate('[...document.querySelectorAll("#'+id+' .leaflet-tile")].every(i=>i.src&&i.complete)'), 'Tiles did not complete.',25000);
            await pause(500);
            const records = [...tileRequests.values()].filter(r=>r.phase===phase);
            const loaded = await evaluate('[...document.querySelectorAll("#'+id+' .leaflet-tile-loaded")].filter(i=>i.naturalWidth===256&&i.naturalHeight===256&&(i.src.startsWith("https://tile.openstreetmap.org/")||i.src.startsWith("blob:"))).length');
            await screenshot(phase+'.png',true);
            results.push({phase,loaded,requests:records});
            if (baseline) {
                for (const [requestId,record] of tileRequests) {
                    if(record.status===403 && record.mimeType?.startsWith('image/')) {
                        const body=await send('Network.getResponseBody',{requestId});
                        await writeFile(path.join(output,'blocked-tile.png'),Buffer.from(body.body,body.base64Encoded?'base64':'utf8'));
                        break;
                    }
                }
            } else {
                assert(loaded>0,'No real tiles rendered for '+phase);
                assert(records.filter(r=>r.status!==undefined).every(r=>r.status===200),'Non-200 tile response for '+phase);
                assert(records.every(r=>r.referer===base+'/'),'Tiles must send origin only, without admin IDs or form data.');
                assert(records.every(r=>!r.requestCacheControl?.includes('no-cache')),'Tiles must honor normal browser caching.');
                assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
                assert.equal(await evaluate('!!document.querySelector("#'+id+' .leaflet-control-attribution a[href=\\"https://www.openstreetmap.org/copyright\\"]")'),true);
                if (name==='admin') {
                    assert.equal(await evaluate('document.getElementById("private-report-map").dataset.latitude'),'14.1234567');
                    assert.equal(await evaluate('document.querySelectorAll(".private-incident-point").length'),1);
                }
                if (name==='report') {
                    await click('#use-map-center');
                    assert.equal(await evaluate('document.querySelectorAll(".incident-point").length'),1);
                }
            }
        }
    }
    if (!baseline) {
        await send('Emulation.setDeviceMetricsOverride',{width:390,height:900,deviceScaleFactor:1,mobile:true});
        await send('Emulation.setTouchEmulationEnabled',{enabled:true,maxTouchPoints:1});
        mobile=true;
        await send('Fetch.enable',{patterns:[{urlPattern:'https://tile.openstreetmap.org/*',requestStage:'Request'}]});
        // Only the simulated outage bypasses browser cache; every tile is intercepted locally.
        await send('Network.setCacheDisabled',{cacheDisabled:true});
        socket.addEventListener('message',({data})=>{
            const event=JSON.parse(data);
            if(event.method==='Fetch.requestPaused') send('Fetch.fulfillRequest',{requestId:event.params.requestId,responseCode:403,responseHeaders:[{name:'Content-Type',value:'image/svg+xml'},{name:'Access-Control-Allow-Origin',value:'*'}],body:Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><text x="10" y="30">403 Access blocked (fixture)</text></svg>').toString('base64')}).catch(()=>{});
        });
        for (const [name,url,id,statusId] of [['admin','/admin/reports/1','private-report-map','private-tiles'],['report','/reports/create','incident-map','incident-tiles'],['public','/map','lake-map','tile-status']]) {
            phase='offline-'+name;
            await navigate(url,'!!document.getElementById("'+id+'")');
            await waitFor(()=>evaluate('!document.getElementById("'+statusId+'").hidden'),'Tile outage must be visible for '+name);
            await click('#'+id+' .leaflet-control-zoom-in');
            await waitFor(()=>evaluate('!document.getElementById("'+statusId+'").hidden'),'Outage handling must survive zoom and tile cancellation.');
            if(name==='admin') {
                assert.equal(await evaluate('document.body.textContent.includes("14.1234567")'),true);
                assert.equal(await evaluate('document.querySelectorAll(".private-incident-point").length'),1);
                assert.equal(await evaluate('document.querySelector("#private-report-map .leaflet-tile").src.startsWith("data:image/svg+xml,")'),true);
            }
            if(name==='report') {
                await click('#use-map-center');
                assert.notEqual(await evaluate('document.getElementById("latitude").value'),'');
                assert.equal(await evaluate('document.querySelector(".incident-point").classList.contains("leaflet-marker-draggable")'),true);
                const beforeDrag=await evaluate('document.getElementById("longitude").value');
                const pin=await evaluate('(()=>{const e=document.querySelector(".incident-point");e.scrollIntoView({block:"center"});const r=e.getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2};})()');
                await send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[pin]});
                for(let n=1;n<=5;n++){await send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x:pin.x+n*8,y:pin.y+n*2}]});await pause(50);}
                await send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
                assert.notEqual(await evaluate('document.getElementById("longitude").value'),beforeDrag);
                await click('#clear-incident');
                await evaluate("Object.defineProperty(navigator,'geolocation',{configurable:true,value:{getCurrentPosition:ok=>ok({coords:{latitude:14.1234567,longitude:121.2345678,accuracy:7.25}})}});");
                await click('#use-current-location');
                assert.equal(await evaluate('document.getElementById("latitude").value'),'','GPS requires confirmation even during a tile outage.');
                await click('#use-gps-location');
                assert.equal(await evaluate('document.getElementById("latitude").value'),'14.1234567');
                assert.equal(await evaluate('document.getElementById("location_accuracy_m").value'),'7.25');
            }
            await screenshot('offline-'+name+'.png',true);
        }
        await send('Fetch.disable');
    }
    assert.deepEqual(failures,[]);
    assert.equal(databaseSnapshot(),originalRecords,'Real database changed.');
    assert.equal(createHash('sha256').update(readFileSync('.env')).digest('hex'),originalEnvironment,'Local environment settings changed.');
    assert.equal(sharedCacheSnapshot(),originalCache,'Shared caches changed.');
    await writeFile(path.join(output,baseline?'baseline.json':process.argv.includes('--simulated-only')?'simulated.json':'results.json'),JSON.stringify({passed:true,baseline,results},null,2));
    console.log(JSON.stringify({passed:true,baseline,results:results.map(r=>({phase:r.phase,loaded:r.loaded,statuses:[...new Set(r.requests.map(t=>t.status))],referers:[...new Set(r.requests.map(t=>t.referer))]})),artifacts:artifactDirectory},null,2));

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
    assert(fixture.startsWith(path.resolve(root,'storage/app/day5-browser')+path.sep));
    await rm(fixture,{recursive:true,force:true,maxRetries:6,retryDelay:200});
}
