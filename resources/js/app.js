import L from 'leaflet';
import './incident-map';

const mapElement = document.getElementById('lake-map');

if (mapElement) {
    const lakes = JSON.parse(document.getElementById('lake-data').textContent);
    const buttons = [...document.querySelectorAll('[data-lake-id]')];
    const status = document.getElementById('map-status');
    const reset = document.getElementById('reset-map');
    const selected = document.getElementById('selected-lake');
    const placeholder = document.getElementById('selection-placeholder');
    const markers = new Map();
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const hasLocation = (lake) => lake.latitude !== null && lake.longitude !== null
        && Number.isFinite(Number(lake.latitude)) && Number.isFinite(Number(lake.longitude))
        && Math.abs(Number(lake.latitude)) <= 90 && Math.abs(Number(lake.longitude)) <= 180;
    const mappedLakes = lakes.filter(hasLocation);
    let map;

    function lakeDetails(lake, headingTag = 'h3') {
        const container = document.createElement('div');
        const heading = document.createElement(headingTag);
        heading.className = 'lake-detail-title';
        heading.textContent = lake.name;
        container.append(heading);
        const metadata = document.createElement('p');
        metadata.className = 'lake-detail-meta';
        metadata.textContent = `${lake.municipality} · ${lake.water_type}`;
        container.append(metadata);
        const description = document.createElement('p');
        description.className = 'lake-detail-description';
        description.textContent = lake.description || 'No description is available for this lake.';
        container.append(description);
        if (lake.source_url && /^https?:\/\//i.test(lake.source_url)) {
            const source = document.createElement('a');
            source.className = 'lake-detail-source';
            source.href = lake.source_url;
            source.target = '_blank';
            source.rel = 'noopener noreferrer';
            source.textContent = 'Read research source';
            source.setAttribute('aria-label', `Read research source for ${lake.name} (opens in a new tab)`);
            container.append(source);
        } else {
            const source = document.createElement('p');
            source.className = 'lake-detail-fallback';
            source.textContent = 'No research source is available for this lake.';
            container.append(source);
        }
        return container;
    }

    function selectLake(lake, moveMap = true) {
        placeholder.hidden = true;
        const details = lakeDetails(lake, 'h2');
        details.querySelector('h2').id = 'selected-lake-heading';
        document.getElementById('lake-information').setAttribute('aria-labelledby', 'selected-lake-heading');
        selected.replaceChildren(details);
        const fishRecords = document.getElementById(`fish-records-${lake.id}`);
        if (fishRecords) {
            const fishDetails = fishRecords.cloneNode(true);
            fishDetails.removeAttribute('id');
            selected.append(fishDetails);
        }
        selected.hidden = false;
        buttons.forEach((button) => {
            const active = button.dataset.lakeId === String(lake.id);
            button.setAttribute('aria-pressed', String(active));
            button.querySelector('.selection-label').textContent = active ? 'Selected' : 'View lake';
        });
        markers.forEach((marker, id) => {
            marker.getElement()?.classList.toggle('is-selected', id === lake.id);
            marker.setZIndexOffset(id === lake.id ? 1000 : 0);
        });
        const marker = markers.get(lake.id);
        if (marker) {
            if (moveMap) {
                map.setView(marker.getLatLng(), Math.max(map.getZoom(), 15), { animate: false });
                if (window.matchMedia('(max-width: 1023px)').matches) {
                    mapElement.scrollIntoView({ behavior: reducedMotion ? 'instant' : 'smooth', block: 'start' });
                }
            }
            marker.getPopup().options.maxWidth = Math.min(300, mapElement.clientWidth - 65);
            marker.openPopup();
            marker.getPopup().update();
            status.textContent = `${lake.name} selected. Use Show all lakes to return to the overview.`;
        } else {
            status.textContent = `${lake.name} selected. Its map location is unavailable; lake details are shown below.`;
        }
    }

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const lake = lakes.find((item) => String(item.id) === button.dataset.lakeId);
            if (lake) {
                selectLake(lake);
            }
        });
    });

    if (mappedLakes.length) {
        map = L.map(mapElement, { zoomControl: false, scrollWheelZoom: false });
        L.control.zoom({ position: 'topright', zoomInTitle: 'Zoom in', zoomOutTitle: 'Zoom out' }).addTo(map);
        L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);
        const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);
        tiles.once('tileerror', () => {
            document.getElementById('tile-status').hidden = false;
        });
        map.on('popupopen', () => { document.getElementById('map-location-note').hidden = true; });
        map.on('popupclose', () => { document.getElementById('map-location-note').hidden = false; });
        const icon = L.divIcon({
            className: 'lake-marker',
            html: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3C9 7 6 11 6 15a6 6 0 0 0 12 0c0-4-3-8-6-12Z"/><path d="M7 15c3-3 5 3 10 0"/></svg>',
            iconSize: [36, 36], iconAnchor: [18, 18], popupAnchor: [0, -18],
        });
        mappedLakes.forEach((lake) => {
            const marker = L.marker([Number(lake.latitude), Number(lake.longitude)], {
                icon, title: lake.name, alt: lake.name, keyboard: true,
            }).addTo(map);
            marker.bindPopup(lakeDetails(lake), {
                maxWidth: Math.min(300, mapElement.clientWidth - 65), maxHeight: 230,
                autoPanPaddingTopLeft: [20, 80], autoPanPaddingBottomRight: [20, 20],
            });
            marker.on('click', () => selectLake(lake, false));
            marker.getElement()?.setAttribute('aria-label', `Select ${lake.name}`);
            markers.set(lake.id, marker);
        });
        const bounds = L.latLngBounds(mappedLakes.map((lake) => [Number(lake.latitude), Number(lake.longitude)]));
        function showAllLakes() {
            map.closePopup();
            map.fitBounds(bounds, { padding: [55, 55], maxZoom: 14, animate: false });
            buttons.forEach((button) => {
                button.setAttribute('aria-pressed', 'false');
                button.querySelector('.selection-label').textContent = 'View lake';
            });
            markers.forEach((marker) => {
                marker.getElement()?.classList.remove('is-selected');
                marker.setZIndexOffset(0);
            });
            selected.hidden = true;
            placeholder.hidden = false;
            document.getElementById('lake-information').setAttribute('aria-labelledby', 'overview-heading');
            status.textContent = `${mappedLakes.length} lake locations shown. Select a marker or a lake from the list.`;
        }
        showAllLakes();
        reset.disabled = false;
        reset.addEventListener('click', showAllLakes);
        new ResizeObserver(() => map.invalidateSize({ pan: false })).observe(mapElement);
    } else {
        status.textContent = 'No mapped locations are available. Any available lake information can still be selected from the list.';
    }
}
