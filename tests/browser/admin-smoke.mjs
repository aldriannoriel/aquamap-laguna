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
const artifactDirectory = 'day5-browser';
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
const fixture = path.resolve(output, 'fixture-'+Date.now());
assert(fixture.startsWith(path.resolve(root, 'storage/app/day5-browser')+path.sep));
await mkdir(fixture, {recursive:true});
for (const folder of ['sessions','photos','cache']) await mkdir(path.join(fixture,folder));
const testPassword=Buffer.from(crypto.getRandomValues(new Uint8Array(24))).toString('hex')+'aA1!';
const environment = {...process.env, AQUAMAP_REPORT_FIXTURE:fixture,
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
    const blocked=await fetch(`${base}/admin/reports/1/photo`,{redirect:'manual'});
    assert.equal(blocked.status,302);
    assert.match(blocked.headers.get('location'),/\/admin\/login$/);
    assert.equal((await fetch(`${base}/admin/login`,{method:'POST',body:new URLSearchParams({email:'admin-fixture@example.test'})})).status,419);
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
    await navigate('/admin','!!document.getElementById("admin-login")');
    await screenshot('desktop-login.png');
    await login('nonadmin-fixture@example.test');
    await waitFor(()=>evaluate('!!document.getElementById("form-errors")'),'Nonadministrator credentials were not rejected.');
    assert.equal(await evaluate('!!document.querySelector("[data-count]")'),false);
    await login('admin-fixture@example.test');
    await waitFor(()=>evaluate('!!document.querySelector("[data-count=total]")'),'Administrator login failed.');
    assert.equal(await evaluate('document.querySelector("[data-count=total]").textContent'),'18');
    assert.equal(await evaluate('document.querySelector("[data-count=pending]").textContent'),'16');
    assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
    await screenshot('desktop-dashboard.png');
    await navigate('/admin/reports','!!document.getElementById("report-filters")');
    assert.equal(await evaluate('document.querySelectorAll("[data-report-id]").length'),15);
    await click('a[href*="page=2"]');
    await waitFor(()=>evaluate('document.querySelectorAll("[data-report-id]").length===3'),'Report pagination failed.');
    await field('q','review target');
    await field('status','pending');
    await field('type','solid_waste');
    await field('lake',String(fixtureInfo.targetLake));
    await field('from',new Date().toISOString().slice(0,10));
    await field('to',new Date().toISOString().slice(0,10));
    await click('#apply-filters');
    await waitFor(()=>evaluate('document.querySelectorAll("[data-report-id]").length===1'),'Combined report filters failed.');
    await screenshot('desktop-filtered.png',true);
    await click('[data-report-id="1"] a');
    await waitFor(()=>evaluate('document.readyState==="complete" && !!document.getElementById("reject-report-form")'),'Private report details did not load.');
    assert.equal(await evaluate('document.body.textContent.includes("synthetic-reporter@example.test")'),true);
    assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
    const photo=await evaluate('fetch(document.getElementById("private-photo").href).then(async r=>({status:r.status,type:r.headers.get("content-type"),cache:r.headers.get("cache-control"),disposition:r.headers.get("content-disposition"),bytes:(await r.arrayBuffer()).byteLength}))');
    assert.equal(photo.status,200);assert.equal(photo.type,'image/png');assert.match(photo.cache,/no-store/);assert(photo.bytes>0);
    assert.equal(photo.disposition.includes('fixture-evidence'),false);
    assert.equal(await evaluate('document.getElementById("reject-report-form").checkValidity()'),false);
    await field('rejection_reason','<script>window.auditXss=true</script> Synthetic duplicate concern only.');
    await click('#reject-report-form button[type=submit]');
    await waitFor(()=>evaluate('document.readyState==="complete" && document.getElementById("report-current-status").textContent.includes("Rejected")'),'Reasoned rejection failed.');
    assert.equal(await evaluate('document.getElementById("audit-history").textContent.includes("Pending to Rejected")'),true);
    assert.equal(await evaluate('window.auditXss===true'),false);
    assert.equal(await evaluate('!!document.getElementById("reject-report-form")'),false);
    assert.equal(await evaluate('!!document.getElementById("report-follow-up-form")'),false);
    assert.equal(await evaluate('document.querySelectorAll("#audit-history li").length'),1);
    await screenshot('desktop-details.png',true);
    await click('#admin-logout');
    await waitFor(()=>evaluate('!!document.getElementById("admin-login")'),'Logout did not return to login.');
    await navigate('/admin/reports/1','!!document.getElementById("admin-login")');
    assert.equal(await evaluate('document.body.textContent.includes("synthetic-reporter@example.test")'),false);
    results.push('Desktop: admin-only login, guest/photo/CSRF rejection, report-status counts, pagination, combined filters, private evidence, required rejection reason, escaped audit notes, terminal state and logout authorization. Forwarded resolution is covered by referrals-smoke.mjs.');
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await send('Emulation.setTouchEmulationEnabled',{enabled:true});mobile=true;
    await login('admin-fixture@example.test');
    await waitFor(()=>evaluate('!!document.querySelector("[data-count=total]")'),'Mobile login failed.');
    assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Mobile dashboard overflow.');
    await screenshot('mobile-dashboard.png',true);
    await navigate('/admin/reports','!!document.getElementById("report-filters")');
    await field('status','pending');await click('#apply-filters');
    await waitFor(()=>evaluate('document.querySelectorAll("[data-report-id]").length===14'),'Mobile filtering failed.');
    assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Mobile report list overflow.');
    await screenshot('mobile-reports.png',true);
    await navigate('/admin/reports/5','!!document.getElementById("reject-report-form")');
    await field('rejection_reason','Synthetic duplicate observation only.');
    await click('#reject-report-form button[type=submit]');
    await waitFor(()=>evaluate('document.getElementById("report-current-status").textContent.includes("Rejected")'),'Mobile rejection failed.');
    assert.equal(await evaluate('!!document.getElementById("reject-report-form")'),false);
    assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Mobile details overflow.');
    await screenshot('mobile-details.png',true);
    await click('#admin-logout');
    await waitFor(()=>evaluate('!!document.getElementById("admin-login")'),'Mobile logout failed.');
    const saved=fixtureCommand('check');
    assert.equal(saved.reports,18);assert.equal(saved.pending,13);assert.equal(saved.verified,1);assert.equal(saved.resolved,1);assert.equal(saved.rejected,3);assert.equal(saved.audits,2);
    results.push('390px mobile: login/logout, responsive counts/filters, private details, required rejection reason, final state and audit history; no horizontal overflow. Two review entries exist only in the isolated fixture.');
    assert.deepEqual(failures, [], 'Browser JavaScript exceptions occurred.');
    assert.equal(databaseSnapshot(), originalRecords, 'Browser verification changed lake or fish records.');
    assert.equal(sharedCacheSnapshot(),originalCache,'Browser fixture changed the shared application cache.');
    results.push('Real MySQL lake, fish, association and pollution report records and shared application cache unchanged.');
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
