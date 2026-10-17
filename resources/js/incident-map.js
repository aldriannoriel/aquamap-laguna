import L from 'leaflet';

const element = document.getElementById('incident-map');
if (element) {
    const lakes = JSON.parse(document.getElementById('incident-lakes').textContent);
    const valid = (lat, lng) => lat !== null && lng !== null && lat !== '' && lng !== ''
        && Number.isFinite(Number(lat)) && Number.isFinite(Number(lng))
        && Math.abs(Number(lat)) <= 90 && Math.abs(Number(lng)) <= 180;
    const mapped = lakes.filter((lake) => valid(lake.latitude, lake.longitude));
    const latitude = document.getElementById('latitude');
    const longitude = document.getElementById('longitude');
    const status = document.getElementById('incident-status');
    const clear = document.getElementById('clear-incident');
    const center = document.getElementById('use-map-center');
    let point;

    if (mapped.length) {
        const map = L.map(element, { scrollWheelZoom: false });
        const bounds = L.latLngBounds(mapped.map((lake) => [Number(lake.latitude), Number(lake.longitude)]));
        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).on('tileerror', () => { document.getElementById('incident-tiles').hidden = false; }).addTo(map);
        for (const lake of mapped) {
            const label = document.createElement('span');
            label.textContent = `${lake.name} — reference location only`;
            L.circleMarker([Number(lake.latitude), Number(lake.longitude)], {
                radius: 7, color: '#277e96', fillOpacity: 0.35, bubblingMouseEvents: false,
            }).bindTooltip(label).addTo(map);
        }
        const select = (location) => {
            if (point) map.removeLayer(point);
            point = L.circleMarker(location, { radius: 10, color: '#9a3412', fillColor: '#ea580c', fillOpacity: 0.85, className: 'incident-point' }).addTo(map);
            latitude.value = location.lat.toFixed(7);
            longitude.value = location.lng.toFixed(7);
            status.textContent = `Incident point selected: ${latitude.value}, ${longitude.value}. This is your observation location, not a verified measurement.`;
            clear.disabled = false;
        };
        map.on('click', (event) => select(event.latlng.wrap()));
        center.disabled = false;
        center.addEventListener('click', () => select(map.getCenter().wrap()));
        clear.addEventListener('click', () => {
            if (point) map.removeLayer(point);
            point = null;
            latitude.value = longitude.value = '';
            status.textContent = 'No incident location selected.';
            clear.disabled = true;
        });
        document.getElementById('water_body_id').addEventListener('change', (event) => {
            const lake = mapped.find((item) => String(item.id) === event.target.value);
            if (lake) map.setView([Number(lake.latitude), Number(lake.longitude)], 15, { animate: false });
            // A lake selection never assigns or changes the user's incident point.
        });
        if (valid(latitude.value, longitude.value)) {
            const restored = L.latLng(Number(latitude.value), Number(longitude.value));
            select(restored);
            map.setView(restored, 15, { animate: false });
        }
        new ResizeObserver(() => map.invalidateSize()).observe(element);
    } else {
        status.textContent = 'Lake reference coordinates are unavailable. Submit without a point and describe nearby landmarks.';
    }
}

const feedback = document.getElementById('form-errors') || document.getElementById('report-success');
if (feedback) feedback.focus();
