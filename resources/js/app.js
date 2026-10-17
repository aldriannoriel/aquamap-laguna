import L from 'leaflet';
import './incident-map';

const mapElement = document.getElementById('lake-map');
if (mapElement) {
    const lakes = JSON.parse(document.getElementById('lake-data').textContent);
    const buttons = [...document.querySelectorAll('[data-lake-id]')];
    const filters = [...document.querySelectorAll('[data-category]')];
    const search = document.getElementById('water-search');
    const status = document.getElementById('map-status');
    const reset = document.getElementById('reset-map');
    const selected = document.getElementById('selected-lake');
    const placeholder = document.getElementById('selection-placeholder');
    const information = document.getElementById('lake-information');
    const features = new Map();
    const markers = new Map();
    let category = 'all';
    let map;
    const normalize = (text) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
    const matchesSearch = (lake) => normalize([lake.name, lake.municipality, ...lake.aliases].join(' ')).includes(normalize(search.value.trim()));
    const visible = () => lakes.filter((lake) => matchesSearch(lake) && (category === 'all' || lake.category === category));

    function clearSelection() {
        map?.closePopup();
        selected.hidden = true;
        placeholder.hidden = false;
        information.setAttribute('aria-labelledby', 'overview-heading');
        buttons.forEach((button) => {
            button.setAttribute('aria-pressed', 'false');
            button.querySelector('.selection-label').textContent = 'View details';
        });
        markers.forEach((marker) => {
            marker.getElement()?.classList.remove('is-selected');
            marker.setZIndexOffset(0);
        });
    }

    function selectLake(lake, moveMap = true) {
        clearSelection();
        const source = document.getElementById('profile-' + lake.id);
        const profile = source.cloneNode(true);
        profile.removeAttribute('id');
        profile.querySelectorAll('[id]').forEach((element) => element.removeAttribute('id'));
        profile.querySelector('h2').id = 'selected-lake-heading';
        information.setAttribute('aria-labelledby', 'selected-lake-heading');
        selected.replaceChildren(profile);
        placeholder.hidden = true;
        selected.hidden = false;
        buttons.forEach((button) => {
            const active = Number(button.dataset.lakeId) === lake.id;
            button.setAttribute('aria-pressed', String(active));
            button.querySelector('.selection-label').textContent = active ? 'Selected' : 'View details';
        });
        const marker = markers.get(lake.id);
        if (marker && map.hasLayer(marker)) {
            marker.getElement()?.classList.add('is-selected');
            marker.setZIndexOffset(1000);
            if (moveMap) {
                const geometry = features.get(lake.id);
                if (geometry) map.fitBounds(geometry.getBounds(), { padding: [45, 65], maxZoom: 15, animate: false });
                else map.setView(marker.getLatLng(), 15, { animate: false });
            }
            marker.getPopup().options.maxWidth = Math.min(270, mapElement.clientWidth - 65);
            marker.openPopup();
            marker.getPopup().update();
            status.textContent = lake.name + ' selected. Source and accuracy limitations are in the details.';
        } else {
            status.textContent = lake.name + ' selected. Location awaiting verification; no marker is displayed.';
        }
        if (moveMap && window.matchMedia('(max-width: 1023px)').matches) {
            information.scrollIntoView({ behavior: 'instant', block: 'start' });
        }
    }

    function fitVisible() {
        const layers = visible().flatMap((lake) => [features.get(lake.id), markers.get(lake.id)]).filter(Boolean);
        if (map && layers.length) map.fitBounds(L.featureGroup(layers).getBounds(), { padding: [40, 55], maxZoom: 14, animate: false });
    }

    function applyFilters() {
        clearSelection();
        const shown = visible();
        const ids = new Set(shown.map((lake) => lake.id));
        buttons.forEach((button) => { button.closest('[data-water-row]').hidden = !ids.has(Number(button.dataset.lakeId)); });
        filters.forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.category === category));
            button.querySelector('[data-category-count]').textContent = lakes.filter((lake) => matchesSearch(lake)
                && (button.dataset.category === 'all' || lake.category === button.dataset.category)).length;
        });
        for (const [id, marker] of markers) {
            if (ids.has(id)) marker.addTo(map);
            else marker.remove();
            const feature = features.get(id);
            if (feature) {
                if (ids.has(id)) feature.addTo(map);
                else feature.remove();
            }
        }
        const mapped = shown.filter((lake) => markers.has(lake.id)).length;
        document.getElementById('filter-empty').hidden = shown.length > 0;
        document.getElementById('filter-status').textContent = shown.length + ' profiles / ' + mapped + ' source-checked map locations.';
        status.textContent = mapped ? mapped + ' locations shown. Select a marker, shape or sidebar item.' : 'No verified map locations match. Change the filters or read available profiles.';
        fitVisible();
    }

    if (lakes.some((lake) => lake.geography)) {
        map = L.map(mapElement, { zoomControl: false, scrollWheelZoom: false });
        L.control.zoom({ position: 'topright', zoomInTitle: 'Zoom in', zoomOutTitle: 'Zoom out' }).addTo(map);
        L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);
        const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);
        tiles.once('tileerror', () => { document.getElementById('tile-status').hidden = false; });
        for (const lake of lakes.filter((item) => item.geography)) {
            const geometry = lake.geography.geometry;
            const feature = L.geoJSON(geometry, { style: { color: '#277e96', weight: lake.category === 'river' ? 5 : 2, fillOpacity: 0.12 } });
            const center = geometry.type === 'Point' ? L.latLng(geometry.coordinates[1], geometry.coordinates[0]) : feature.getBounds().getCenter();
            if (geometry.type !== 'Point') {
                feature.on('click', () => selectLake(lake, false));
                feature.on('add', () => feature.eachLayer((layer) => {
                    const element = layer.getElement?.();
                    if (!element) return;
                    element.setAttribute('tabindex', '0');
                    element.setAttribute('role', 'button');
                    element.setAttribute('aria-label', 'Select shape for ' + lake.name);
                    element.onkeydown = (event) => {
                        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); selectLake(lake, false); }
                    };
                }));
                features.set(lake.id, feature);
            }
            const icon = L.divIcon({
                className: 'lake-marker category-' + lake.category,
                html: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3C9 7 6 11 6 15a6 6 0 0 0 12 0c0-4-3-8-6-12Z"/><path d="M7 15c3-3 5 3 10 0"/></svg>',
                iconSize: [36, 36], iconAnchor: [18, 18], popupAnchor: [0, -18],
            });
            const marker = L.marker(center, { icon, title: lake.name, alt: lake.name, keyboard: true });
            const popup = document.createElement('div');
            const title = document.createElement('h3');
            title.className = 'lake-detail-title';
            title.textContent = lake.name;
            const location = document.createElement('p');
            location.textContent = lake.municipality + (lake.site_type === 'resort' ? ' / Resort location' : '');
            const hint = document.createElement('p');
            hint.textContent = 'Profile and sources are shown below the map.';
            popup.append(title, location, hint);
            marker.bindPopup(popup, { maxWidth: 270, autoPanPaddingTopLeft: [20, 65], autoPanPaddingBottomRight: [20, 20] });
            marker.on('click', () => selectLake(lake, false));
            marker.on('add', () => marker.getElement()?.setAttribute('aria-label', 'Select ' + lake.name));
            markers.set(lake.id, marker);
        }
        new ResizeObserver(() => map.invalidateSize({ pan: false })).observe(mapElement);
    }
    buttons.forEach((button) => button.addEventListener('click', () => selectLake(lakes.find((lake) => lake.id === Number(button.dataset.lakeId)))));
    filters.forEach((button) => button.addEventListener('click', () => { category = button.dataset.category; applyFilters(); }));
    search.addEventListener('input', applyFilters);
    reset.disabled = false;
    reset.addEventListener('click', () => { category = 'all'; search.value = ''; applyFilters(); });
    applyFilters();
}
