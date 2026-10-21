import assert from 'node:assert/strict';
import { readFile, writeFile, access, copyFile, mkdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// Explicit maintenance task only; never called by a public request or the test suite.
assert.equal(process.cwd(), path.dirname(path.dirname(fileURLToPath(import.meta.url))), 'Run from the AquaMap project root.');
const directory = path.resolve('storage/app/research-audit');
await mkdir(directory, { recursive: true });
if (!existsSync(path.join(directory, 'baseline-links.json')) || process.argv.includes('--refresh-inventory') || process.argv.includes('--export-only')) {
    const exported = spawnSync('php', [], { windowsHide: true, encoding: 'utf8', input: String.raw`<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'aquamap_laguna'
    || $db->selectOne('SELECT DATABASE() AS name', [], false)->name !== 'aquamap_laguna') { exit(1); }
$data = app(App\Http\Controllers\MapController::class)->index()->getData();
$links = [];
$profiles = [];
foreach ($data['lakes'] as $lake) {
    $research = $data['profiles'][$lake['id']]['research'];
    $profile = ['name'=>$lake['name'], 'source_uses'=>$research['source_uses'], 'citations'=>count($research['citations']), 'duplicates_removed'=>$research['duplicates_removed'], 'excluded'=>count($research['excluded']), 'missing_title'=>0, 'missing_publisher'=>0, 'missing_publication_year'=>0];
    foreach ($research['citations'] as $source) {
        foreach (['title'=>'missing_title','publisher'=>'missing_publisher','year'=>'missing_publication_year'] as $field=>$counter) { if ($source[$field]===null) { $profile[$counter]++; } }
        foreach (array_unique([$source['url'], ...$source['locations']]) as $url) { $links[] = ['profile'=>$lake['name'], 'url'=>$url]; }
    }
    $profiles[] = $profile;
}
echo json_encode(['profiles'=>$profiles,'links'=>$links], JSON_THROW_ON_ERROR);
` });
    assert.equal(exported.status, 0, 'Research export requires the verified project database.');
    const inventory = JSON.parse(exported.stdout);
    const links = inventory.links;
    assert(Array.isArray(links) && links.length > 0);
    await writeFile(path.join(directory, 'baseline-links.json'), JSON.stringify(links, null, 2)+'\n');
    await writeFile(path.join(directory, 'inventory.json'), JSON.stringify(inventory, null, 2)+'\n');
}
if (process.argv.includes('--export-only')) { console.log('Guarded public inventory exported; no network checks requested.'); process.exit(0); }
const rows = JSON.parse(await readFile(path.join(directory, 'baseline-links.json'), 'utf8'));
const allUrls = [...new Set(rows.map(row => row.url.split('#')[0]))].sort();
const previous = process.argv.includes('--retry') ? JSON.parse(await readFile(path.join(directory, 'link-checks.json'), 'utf8')) : [];
if (previous.length && !existsSync(path.join(directory, 'link-checks-initial.json'))) await copyFile(path.join(directory, 'link-checks.json'), path.join(directory, 'link-checks-initial.json'));
const results = previous.filter(row => row.status === 'reachable');
const urls = allUrls.filter(url => !results.some(row => row.url === url));
for (let start = 0; start < urls.length; start += 12) {
    for (let offset = start; offset < Math.min(start + 12, urls.length); offset += 3) {
        await Promise.all(urls.slice(offset, Math.min(offset + 3, start + 12)).map(async url => {
            const parsed = new URL(url);
            assert(['https:', 'http:'].includes(parsed.protocol));
            const prior = previous.find(row => row.url === url);
            const result = { url, checked_on: new Date().toISOString().slice(0, 10), attempts: (prior?.attempts ?? (prior ? 1 : 0)) + 1 };
            if (parsed.hostname === 'aquamap-laguna.test' && parsed.pathname === '/images/water-bodies/document-photo-permission.html') {
                await access(path.resolve('public/images/water-bodies/document-photo-permission.html'));
                results.push({ ...result, status: 'reachable', method: 'local_file' });
                return;
            }
            try {
                const response = await fetch(url, { redirect: 'follow', signal: AbortSignal.timeout(12000), headers: {
                    'User-Agent': 'AquaMap-Laguna-Research-Link-Audit/1.0', Range: 'bytes=0-65535',
                } });
                let text = '';
                if ((response.headers.get('content-type') || '').includes('text/html') && response.body) {
                    const reader = response.body.getReader();
                    while (text.length < 65536) {
                        const chunk = await reader.read();
                        if (chunk.done) break;
                        text += new TextDecoder().decode(chunk.value);
                    }
                    await reader.cancel();
                } else {
                    await response.body?.cancel();
                }
                const challenge = /<title>[^<]*(just a moment|access denied|security check|robot check)/i.test(text);
                results.push({ ...result, status: response.ok && !challenge ? 'reachable' : 'unresolved',
                    http_status: response.status, final_url: response.url,
                    reason: challenge ? 'Automated-access challenge' : response.ok ? 'HTTP success; claim validity reviewed separately' : 'HTTP response is not proof the source is invalid',
                });
            } catch (error) {
                results.push({ ...result, status: 'unresolved', reason: `${error.name}: network access not confirmed` });
            }
        }));
    }
    await writeFile(path.join(directory, 'link-checks.json'), JSON.stringify(results.sort((a, b) => a.url.localeCompare(b.url)), null, 2)+'\n');
    console.log(JSON.stringify({ checked: results.length, total: allUrls.length, reachable: results.filter(row => row.status === 'reachable').length }));
}
