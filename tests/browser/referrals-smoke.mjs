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
    $snapshot = [];
    foreach (['water_bodies','fish_species','water_body_fish','pollution_reports','users','report_reviews','agencies','agency_water_body','agency_directory_initializations','referrals','referral_events','jobs','failed_jobs','migrations'] as $table) {
        $rows = $connection->table($table)->get()->map(fn ($row) => (array) $row)->all();
        usort($rows, fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
        $snapshot[$table] = $rows;
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
const originalEnvironment = createHash('sha256').update(readFileSync(path.join(root, '.env'))).digest('hex');
function photoSnapshot() {
    const directory = path.join(root, 'storage/app/private/pollution-reports');
    if (!existsSync(directory)) return '[]';
    return JSON.stringify(readdirSync(directory, { recursive:true, withFileTypes:true }).filter(file=>file.isFile())
        .map(file=>{const name=path.join(file.parentPath,file.name); return [path.relative(directory,name),createHash('sha256').update(readFileSync(name)).digest('hex')];})
        .sort((a,b)=>a[0].localeCompare(b[0])));
}
const originalPhotos = photoSnapshot();
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
const artifactDirectory = 'legacy-delivery-ui/browser';
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
    await send('Fetch.enable',{patterns:[{urlPattern:'https://tile.openstreetmap.org/*',requestStage:'Request'}]});
    socket.addEventListener('message',({data})=>{const event=JSON.parse(data);if(event.method==='Fetch.requestPaused')send('Fetch.fulfillRequest',{requestId:event.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'image/png'},{name:'Access-Control-Allow-Origin',value:'*'}],body:readFileSync(path.join(fixture,'basemap.png')).toString('base64')}).catch(()=>{});});


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
    await navigate('/reports/create','!!document.getElementById("use-current-location")');
    assert.equal(await evaluate('document.getElementById("latitude").value'),'');
    await evaluate("window.gpsCalls=0;Object.defineProperty(navigator,'geolocation',{configurable:true,value:{getCurrentPosition:(ok,fail)=>{window.gpsCalls++;fail({code:1});}}});");
    await click('#use-current-location');
    assert.equal(await evaluate('window.gpsCalls'),1);
    assert.equal(await evaluate('document.getElementById("gps-status").textContent.includes("denied")'),true);
    await click('#use-map-center');
    assert.equal(await evaluate('document.querySelectorAll(".incident-point").length'),1);
    const beforeDrag=await evaluate('document.getElementById("longitude").value');
    const pin=await evaluate('(()=>{const r=document.querySelector(".incident-point").getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2};})()');
    await send('Input.dispatchMouseEvent',{type:'mousePressed',...pin,button:'left',clickCount:1});
    for(let n=1;n<=5;n++){await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:pin.x+n*9,y:pin.y+n*3,button:'left',buttons:1});await pause(50);}
    await send('Input.dispatchMouseEvent',{type:'mouseReleased',x:pin.x+45,y:pin.y+15,button:'left',clickCount:1});
    assert.notEqual(await evaluate('document.getElementById("longitude").value'),beforeDrag);
    await click('#clear-incident');
    await evaluate("Object.defineProperty(navigator,'geolocation',{configurable:true,value:undefined});");
    await click('#use-current-location');
    assert.equal(await evaluate('document.getElementById("gps-status").textContent.includes("unavailable")'),true);
    await evaluate("Object.defineProperty(navigator,'geolocation',{configurable:true,value:{getCurrentPosition:ok=>ok({coords:{latitude:14.1234567,longitude:121.2345678,accuracy:7.25}})}});");
    await click('#use-current-location');
    assert.equal(await evaluate('document.getElementById("latitude").value'),'','GPS must not silently become the incident point.');
    assert.equal(await evaluate('document.getElementById("gps-status").textContent.includes("7 metres")'),true);
    await click('#use-gps-location');
    assert.equal(await evaluate('document.getElementById("latitude").value'),'14.1234567');
    assert.equal(await evaluate('document.getElementById("location_accuracy_m").value'),'7.25');
    await screenshot('desktop-location.png',true);
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await send('Emulation.setTouchEmulationEnabled',{enabled:true,maxTouchPoints:1});mobile=true;
    await navigate('/reports/create','!!document.getElementById("use-map-center")');
    await click('#use-map-center');assert.equal(await evaluate('document.querySelectorAll(".incident-point").length'),1);
    assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
    await screenshot('mobile-location.png',true);
    results.push('1440/390px: manual pin, trusted drag, explicit GPS request/confirmation, denied/unavailable fallback, accuracy and coordinate review passed. GPS responses were simulated.');
    await send('Emulation.setTouchEmulationEnabled',{enabled:false});mobile=false;
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
    await navigate('/admin/login','!!document.getElementById("admin-sign-in")');await login('admin-fixture@example.test');
    await waitFor(()=>evaluate('document.querySelector("[aria-label=\\"Administrator navigation\\"]")!==null'),'Admin sign-in did not complete.');
    assert.equal(await evaluate('document.body.textContent.includes("Referrals & email test")'),false);
    await navigate('/admin','!!document.querySelector("[data-count=total]")');
    assert.equal(await evaluate('document.querySelector("[data-count=total]").textContent'),'18');
    for (const queue of ['all','action','processed']) {
        await navigate('/admin/reports?queue='+queue,'!!document.getElementById("admin-report-list")');
        const text = await evaluate('document.body.textContent');
        assert.equal(text.includes('Preserved legacy delivery history'),false);
        assert.equal(text.includes('Retired diagnostic browser'),false);
        for (const reference of fixtureInfo.legacyReferences) assert.equal(text.includes(reference),false);
        if (queue === 'processed') assert.equal(await evaluate('document.querySelectorAll("#admin-report-list article").length'),2);
    }
    await screenshot('real-reports-only-desktop.png',true);
    await navigate('/admin/agencies/create','!!document.getElementById("verification_status")');
    await field('name','Browser municipality recipient');await field('municipality','San Pablo City');await field('email','aquamaplaguna@gmail.com');
    await click('main form .primary-button');
    await waitFor(()=>evaluate('document.readyState==="complete" && document.body.textContent.includes("Recipient added.")'),'Directory create failed.');
    await screenshot('directory.png',true);
    await navigate('/admin/reports/1','!!document.getElementById("send-to-municipality")');
    assert.equal(await evaluate('document.getElementById("agency_id").value'),'1');
    assert.equal(await evaluate('document.body.textContent.includes("Record decision")'),false);
    assert.equal(await evaluate('document.getElementById("private-report-map").dataset.latitude'),'14.1234567');
    await evaluate('document.getElementById("include_photo").checked=true');
    await click('#send-to-municipality');
    assert.equal(await evaluate('document.getElementById("municipality-confirmation").open'),true);
    assert.equal(await evaluate('document.getElementById("send-message-preview").textContent.includes("synthetic-reporter@example.test")'),false);
    assert.equal(await evaluate('document.getElementById("municipality-confirm-heading").getBoundingClientRect().top>=0'),true);
    await screenshot('confirmation-desktop.png');
    await click('#cancel-municipality-send');
    assert.equal(fixtureCommand('deliver-referrals').accepted,0);
    await click('#send-to-municipality');
    await evaluate('document.querySelector("#municipality-confirmation input[name=approved]").checked=true');
    assert.equal(await evaluate('(()=>{const form=document.getElementById("municipality-send-form");form.requestSubmit();const disabled=document.getElementById("confirm-municipality-send").disabled;form.requestSubmit();return disabled;})()'),true);
    await waitFor(()=>evaluate('document.readyState==="complete" && document.body.textContent.includes("Referral queued for sending.")'),'Queue acknowledgement missing.');
    assert.equal(await evaluate('document.body.textContent.includes("Referral email sent successfully")'),false);
    await screenshot('queued.png',true);
    assert.equal(fixtureCommand('deliver-referrals').accepted,1);
    assert.equal(fixtureCommand('deliver-referrals').accepted,1);
    await waitFor(()=>evaluate('document.readyState==="complete" && document.body.textContent.includes("Referral email sent successfully")'),'Transport acceptance did not update the report.');
    await screenshot('accepted.png',true);
    const reference=await evaluate('document.querySelector("[aria-label=\\"Delivery history\\"] h3").textContent.trim()');
    await field('confirmation_reference',reference);
    await evaluate('document.querySelector("form[action$=\\"/confirm\\"] input[name=approved]").checked=true');
    await click('form[action$="/confirm"] .secondary-button');
    await waitFor(()=>evaluate('document.body.textContent.includes("Receiver inbox receipt recorded")'),'Receipt confirmation failed.');
    await navigate('/admin/reports?queue=action&q=Synthetic%20browser%20review%20target','!!document.getElementById("admin-report-list")');
    assert.equal(await evaluate('document.querySelector("article[data-report-id=\\"1\\"]")===null'),true);
    await navigate('/admin/reports?queue=processed&q=Synthetic%20browser%20review%20target','!!document.getElementById("admin-report-list")');
    assert.equal(await evaluate('document.body.textContent.includes("Preserved legacy delivery history")'),false);
    assert.equal(await evaluate('document.querySelector("article[data-report-id=\\"1\\"]")!==null'),true);
    await screenshot('processed.png',true);
    await navigate('/admin/reports/1','!!document.getElementById("report-follow-up-form")');
    assert.equal(await evaluate('document.getElementById("report-current-status").textContent.includes("Verified")'),true);
    assert.equal(await evaluate('!!document.getElementById("reject-report-form")'),false);
    await field('follow_up_status','under_review'); await field('review_note','Synthetic municipality review started.');
    await click('#report-follow-up-form button[type=submit]');
    await waitFor(()=>evaluate('document.readyState==="complete" && document.getElementById("report-current-status")?.textContent.includes("Under Review")'),'Under Review update failed.');
    await field('follow_up_status','in_progress'); await field('review_note','Synthetic cleanup in progress.');
    await click('#report-follow-up-form button[type=submit]');
    await waitFor(()=>evaluate('document.readyState==="complete" && document.getElementById("report-current-status")?.textContent.includes("In Progress")'),'In Progress update failed.');
    await screenshot('follow-up-desktop.png',true);
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await send('Emulation.setTouchEmulationEnabled',{enabled:true,maxTouchPoints:1});mobile=true;
    await navigate('/admin/reports/2','!!document.getElementById("send-to-municipality")');
    await click('#send-to-municipality');
    assert.equal(await evaluate('document.getElementById("municipality-confirmation").open'),true);
    assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
    assert.equal(await evaluate('document.getElementById("municipality-confirm-heading").getBoundingClientRect().top>=0'),true);
    await screenshot('confirmation-mobile.png');
    await click('#cancel-municipality-send');
    assert.equal(fixtureCommand('deliver-referrals').accepted,1);
    assert.equal(await evaluate('document.getElementById("reject-report-form").checkValidity()'),false);
    await field('rejection_reason','Synthetic duplicate concern; see fixture case 02.');
    await click('#reject-report-form button[type=submit]');
    await waitFor(()=>evaluate('document.readyState==="complete" && document.getElementById("report-current-status")?.textContent.includes("Rejected")'),'Reasoned mobile rejection failed.');
    assert.equal(await evaluate('!!document.getElementById("send-to-municipality")'),false);
    await screenshot('rejected-mobile.png',true);
    await navigate('/admin/reports/1','!!document.getElementById("report-follow-up-form")');
    await field('follow_up_status','resolved');
    assert.equal(await evaluate('document.getElementById("review_note").required && document.getElementById("resolution_basis").required'),true);
    assert.equal(await evaluate('document.getElementById("report-follow-up-form").checkValidity()'),false);
    await field('review_note','Synthetic follow-up inspection completed.');
    assert.equal(await evaluate('document.getElementById("report-follow-up-form").checkValidity()'),false);
    await field('resolution_basis','Synthetic municipality inspection reference FIXTURE-RESOLUTION confirms cleanup.');
    assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
    await screenshot('resolution-mobile.png',true);
    await click('#report-follow-up-form button[type=submit]');
    await waitFor(()=>evaluate('document.readyState==="complete" && document.getElementById("report-current-status")?.textContent.includes("Resolved")'),'Documented resolution failed.');
    assert.equal(await evaluate('document.getElementById("report-current-status").textContent.includes("Sent / accepted by SMTP")'),true);
    assert.equal(await evaluate('!!document.getElementById("report-follow-up-form")'),false);
    assert.equal(fixtureCommand('deliver-referrals').accepted,1);
    await screenshot('resolved-mobile.png',true);
    await send('Emulation.setTouchEmulationEnabled',{enabled:false});mobile=false;
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
    await navigate('/admin/agencies/1/edit','!!document.getElementById("notes")');
    await field('notes','Browser verified municipality edit');await click('main form .primary-button');
    await waitFor(()=>evaluate('document.readyState==="complete" && document.body.textContent.includes("Recipient updated.")'),'Directory edit failed.');
    await navigate('/admin/agencies/1/edit','!!document.querySelector("input[name=confirm_delete]")');
    await evaluate('document.querySelector("input[name=confirm_delete]").checked=true');
    await click('form input[name=_method][value=DELETE] ~ button');
    await waitFor(()=>evaluate('document.readyState==="complete" && document.body.textContent.includes("Recipient deleted.")'),'Directory delete failed.');
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await navigate('/admin/reports/1','document.body.textContent.includes("Browser municipality recipient")');
    assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
    await screenshot('history-mobile.png',true);
    await navigate('/admin/reports?queue=processed','!!document.getElementById("admin-report-list")');
    assert.equal(await evaluate('document.body.textContent.includes("Preserved legacy delivery history")'),false);
    assert.equal(await evaluate('document.body.textContent.includes("Retired diagnostic browser")'),false);
    assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
    assert.equal(fixtureCommand('deliver-referrals').retiredQueued,1);
    await screenshot('real-reports-only-mobile.png',true);
    results.push('Retired queued/accepted/failed diagnostics are hidden from all report lists and processed history; real referral history remains visible, real-report totals stay correct and queued diagnostics stay unsent.');
    results.push('Desktop/mobile: confirmed direct send, duplicate prevention, isolated worker, distinct report/email statuses, forwarded grouping, Under Review/In Progress, required rejection reason, required resolution remarks/basis, final-state history and unchanged accepted referral passed. Municipality CRUD/snapshots and private map controls passed. Mail::fake() and simulated tiles/GPS only; no real email sent.');

    assert.deepEqual(failures, [], 'Browser JavaScript exceptions occurred.');
    assert.equal(databaseSnapshot(), originalRecords, 'Browser verification changed lake or fish records.');
    assert.equal(sharedCacheSnapshot(),originalCache,'Browser fixture changed the shared application cache.');
    assert.equal(photoSnapshot(),originalPhotos,'Browser fixture changed original private evidence.');
    assert.equal(createHash('sha256').update(readFileSync(path.join(root,'.env'))).digest('hex'),originalEnvironment,'Browser fixture changed local credentials.');
    results.push('All inspected real MySQL domain/report/audit/directory/referral/event/queue/migration rows, original photos, shared cache and .env unchanged.');
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
    assert(fixture.startsWith(path.resolve(root,'storage/app/day5-browser')+path.sep));
    await rm(fixture,{recursive:true,force:true,maxRetries:6,retryDelay:200});
}
