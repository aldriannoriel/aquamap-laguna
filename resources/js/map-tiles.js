import L from 'leaflet';

// Standard OSM raster tiles: https://operations.osmfoundation.org/policies/tiles/
const provider = {
    url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
};
const unavailableTile = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><path fill="#eef7f8" d="M0 0h256v256H0z"/></svg>');

export function addMapTiles(map, message) {
    const failures = new Set();
    const requests = new WeakMap();
    const updateMessage = () => { if (message) message.hidden = failures.size === 0; };
    const tiles = L.gridLayer({
        maxZoom: 19,
        attribution: provider.attribution,
        referrerPolicy: 'strict-origin-when-cross-origin',
        updateWhenIdle: true,
        keepBuffer: 1,
    });
    // Images can render an HTTP 403 error graphic without firing an error event.
    // Check status using one normally cached, anonymous CORS request per tile.
    tiles.createTile = function (coords, done) {
        const tile = document.createElement('img');
        tile.alt = '';
        const controller = new AbortController();
        const request = { controller, url: null, removed: false, timer: setTimeout(() => controller.abort(), 20000) };
        requests.set(tile, request);
        const fail = () => {
            clearTimeout(request.timer);
            if (request.removed) return;
            tile.onload = tile.onerror = null;
            tile.src = unavailableTile;
            done(new Error('Basemap tile unavailable.'), tile);
        };
        tile.onload = () => { clearTimeout(request.timer); done(null, tile); };
        tile.onerror = fail;
        fetch(L.Util.template(provider.url, coords), {
            mode: 'cors', credentials: 'omit', cache: 'default',
            referrerPolicy: this.options.referrerPolicy, signal: controller.signal,
        }).then(response => {
            if (!response.ok || !response.headers.get('content-type')?.startsWith('image/png')) throw new Error('Tile rejected.');
            return response.blob();
        }).then(blob => {
            if (request.removed) return;
            if (controller.signal.aborted) { fail(); return; }
            request.url = URL.createObjectURL(blob);
            tile.src = request.url;
        }).catch(fail);
        return tile;
    };
    tiles.on('tileerror', ({tile}) => { failures.add(tile); updateMessage(); });
    tiles.on('tileload', ({tile}) => {
        if (tile.src !== unavailableTile) failures.delete(tile);
        updateMessage();
    });
    tiles.on('tileunload', ({tile}) => {
        const request = requests.get(tile);
        if (request) {
            request.removed = true;
            clearTimeout(request.timer);
            request.controller.abort();
            if (request.url) URL.revokeObjectURL(request.url);
            tile.onload = tile.onerror = null;
            requests.delete(tile);
        }
        failures.delete(tile); updateMessage();
    });
    return tiles.addTo(map);
}
