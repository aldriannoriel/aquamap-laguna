import L from 'leaflet';
import './report-categories';
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
    const dialog = document.getElementById('water-detail-dialog');
    const information = document.getElementById('lake-information');
    const profileTabs = [...dialog.querySelectorAll('[data-profile-tab]')];
    const features = new Map();
    const markers = new Map();
    const selectionCard = document.getElementById('map-selection');
    const selectionSummary = document.getElementById('map-selection-summary');
    let selectedId = null;
    let category = 'all';
    let map;
    const normalize = (text) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
    const matchesSearch = (lake) => normalize([lake.name, lake.municipality, ...lake.aliases].join(' ')).includes(normalize(search.value.trim()));
    const visible = () => lakes.filter((lake) => matchesSearch(lake) && (category === 'all' || lake.category === category));

    function styleSelection() {
        features.forEach((feature, id) => {
            const active = id === selectedId;
            const secondary = selectedId !== null && !active;
            const river = lakes.find((lake) => lake.id === id).category === 'river';
            feature.setStyle({ color: active ? '#0066ff' : '#277e96', weight: active ? (river ? 7 : 4) : (river ? 4 : 2),
                opacity: secondary ? 0.35 : 1, fillColor: active ? '#0066ff' : '#277e96', fillOpacity: active ? 0.24 : (secondary ? 0.04 : 0.12),
                className: active ? 'selected-water-course' : '' });
            feature.eachLayer((layer) => layer.getElement?.()?.classList.toggle('selected-water-course', active));
            if (active) feature.bringToFront();
        });
        markers.forEach((marker, id) => marker.setOpacity(selectedId !== null && id !== selectedId ? 0.5 : 1));
    }

    function positionDialog() {
        if (!dialog.open) return;
        const bounds = mapElement.getBoundingClientRect();
        dialog.style.setProperty('--profile-map-half', bounds.width / 2 + 'px');
        const frame = dialog.getBoundingClientRect();
        dialog.style.setProperty('--profile-left', Math.max(16, Math.min(bounds.left + (bounds.width - frame.width) / 2, document.documentElement.clientWidth - frame.width - 16)) + 'px');
        dialog.style.setProperty('--profile-top', Math.max(16, Math.min(bounds.top + (bounds.height - frame.height) / 2, window.innerHeight - frame.height - 16)) + 'px');
    }

    function activateProfileTab(key, moveFocus = false) {
        profileTabs.forEach((tab) => {
            const active = tab.dataset.profileTab === key;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
            if (active && moveFocus) {
                tab.focus({ preventScroll: true });
                tab.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            }
        });
        selected.querySelectorAll('[data-profile-section]').forEach((panel) => { panel.hidden = panel.dataset.profileSection !== key; });
        information.scrollTop = 0;
    }

    function clearSelection() {
        if (dialog.open) dialog.close();
        selectedId = null;
        selectionCard.hidden = true;
        selectionSummary.replaceChildren();
        styleSelection();
        status.textContent = 'Selection cleared. Select another water body.';
        document.documentElement.classList.remove('profile-open');
        selected.hidden = true;
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
        selectedId = lake.id;
        const source = document.getElementById('profile-' + lake.id);
        const profile = source.cloneNode(true);
        profile.removeAttribute('id');
        profile.querySelectorAll('[id]').forEach((element) => element.removeAttribute('id'));
        profile.querySelector('h2').remove();
        document.getElementById('selected-lake-heading').textContent = lake.name;
        profile.querySelectorAll('[data-profile-section]').forEach((panel) => {
            const key = panel.dataset.profileSection;
            panel.id = 'profile-panel-' + key;
            panel.setAttribute('role', 'tabpanel');
            panel.removeAttribute('aria-label');
            panel.setAttribute('aria-labelledby', 'profile-tab-' + key);
            panel.tabIndex = 0;
            panel.classList.remove('mt-5');
        });
        selected.replaceChildren(profile);
        selected.hidden = false;
        activateProfileTab('overview');
        document.getElementById('profile-tabs').scrollLeft = 0;
        document.getElementById('map-selection-heading').textContent = lake.name;
        const geographicSummary = profile.querySelector('[data-geography-summary]');
        selectionSummary.replaceChildren();
        if (geographicSummary) selectionSummary.append(geographicSummary.cloneNode(true));
        else selectionSummary.textContent = lake.geography?.note || 'Location awaiting verification.';
        selectionCard.hidden = false;
        map?.invalidateSize({ pan: false });
        styleSelection();
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

            status.textContent = lake.name + ' selected. Source and accuracy limitations are in the details.';
        } else {
            status.textContent = lake.name + ' selected. Location awaiting verification; no marker is displayed.';
        }
        if (window.innerWidth < 1024) selectionCard.scrollIntoView({ block: 'start', behavior: 'instant' });
        information.scrollTop = 0;
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
                feature.on('click', () => selectLake(lake));
                feature.on('add', () => feature.eachLayer((layer) => {
                    const element = layer.getElement?.();
                    if (!element) return;
                    element.setAttribute('tabindex', '0');
                    element.setAttribute('role', 'button');
                    element.setAttribute('aria-label', 'Select shape for ' + lake.name);
                    element.onkeydown = (event) => {
                        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); selectLake(lake); }
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
            marker.on('click', () => selectLake(lake));
            marker.on('add', () => marker.getElement()?.setAttribute('aria-label', 'Select ' + lake.name));
            markers.set(lake.id, marker);
        }
        new ResizeObserver(() => map.invalidateSize({ pan: false })).observe(mapElement);
    }
    document.getElementById('close-water-detail').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        if (!dialog.open) {
            const trigger = buttons.find((button) => Number(button.dataset.lakeId) === selectedId);
            clearSelection();
            trigger?.focus({ preventScroll: true });
        }
    });
    document.getElementById('clear-water-selection').addEventListener('click', () => {
        const trigger = buttons.find((button) => Number(button.dataset.lakeId) === selectedId);
        clearSelection();
        trigger?.focus({ preventScroll: true });
    });
    document.getElementById('open-water-profile').addEventListener('click', () => {
        dialog.showModal();
        document.documentElement.classList.add('profile-open');
        positionDialog();
    });
    profileTabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activateProfileTab(tab.dataset.profileTab));
        tab.addEventListener('keydown', (event) => {
            const next = { ArrowRight: (index + 1) % profileTabs.length, ArrowLeft: (index + profileTabs.length - 1) % profileTabs.length, Home: 0, End: profileTabs.length - 1 }[event.key];
            if (next === undefined) return;
            event.preventDefault();
            activateProfileTab(profileTabs[next].dataset.profileTab, true);
        });
    });
    new ResizeObserver(positionDialog).observe(mapElement);
    window.addEventListener('resize', positionDialog);
    window.addEventListener('scroll', positionDialog, { passive: true });
    buttons.forEach((button) => button.addEventListener('click', () => selectLake(lakes.find((lake) => lake.id === Number(button.dataset.lakeId)))));
    filters.forEach((button) => button.addEventListener('click', () => { category = button.dataset.category; applyFilters(); }));
    search.addEventListener('input', applyFilters);
    reset.disabled = false;
    reset.addEventListener('click', () => { category = 'all'; search.value = ''; applyFilters(); });
    applyFilters();
}
