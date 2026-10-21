import L from 'leaflet';
import { addMapTiles } from './map-tiles';

const element = document.getElementById('incident-map');
if (element) {
    const lakes = JSON.parse(document.getElementById('incident-lakes').textContent);
    const valid = (lat, lng) => lat !== null && lng !== null && lat !== '' && lng !== ''
        && Number.isFinite(Number(lat)) && Number.isFinite(Number(lng))
        && Math.abs(Number(lat)) <= 90 && Math.abs(Number(lng)) <= 180;
    const mapped = lakes.filter(lake => valid(lake.latitude, lake.longitude));
    const latitude = document.getElementById('latitude');
    const longitude = document.getElementById('longitude');
    const accuracy = document.getElementById('location_accuracy_m');
    const status = document.getElementById('incident-status');
    const coordinates = document.getElementById('incident-coordinates');
    const clear = document.getElementById('clear-incident');
    const center = document.getElementById('use-map-center');
    const gps = document.getElementById('use-current-location');
    const confirmGps = document.getElementById('use-gps-location');
    const gpsStatus = document.getElementById('gps-status');
    let point, candidate, accuracyCircle;
    let gpsRequest = 0;
    const map = L.map(element, { scrollWheelZoom: false }).setView([14.25, 121.35], 10);
    if (mapped.length) map.fitBounds(L.latLngBounds(mapped.map(lake => [Number(lake.latitude), Number(lake.longitude)])), { padding: [30, 30], maxZoom: 14 });
    addMapTiles(map, document.getElementById('incident-tiles'));
    for (const lake of mapped) {
        const label = document.createElement('span'); label.textContent = lake.name + ' / reference location only';
        L.circleMarker([Number(lake.latitude), Number(lake.longitude)], { radius: 7, color: '#277e96', fillOpacity: 0.35, bubblingMouseEvents: false }).bindTooltip(label).addTo(map);
    }
    const update = (location, metres = null) => {
        latitude.value = location.lat.toFixed(7); longitude.value = location.lng.toFixed(7);
        accuracy.value = Number.isFinite(metres) ? metres.toFixed(2) : '';
        coordinates.textContent = 'Latitude ' + latitude.value + ', longitude ' + longitude.value;
        status.textContent = 'Problem-location pin selected. Review these coordinates before submitting. Drag the pin or tap the map to reposition it.';
        clear.disabled = false;
    };
    const select = (location, metres = null) => {
        gpsRequest++;
        gps.disabled = false;
        candidate = null; confirmGps.hidden = true;
        if (accuracyCircle) { map.removeLayer(accuracyCircle); accuracyCircle = null; }
        if (point) map.removeLayer(point);
        point = L.marker(location, {
            draggable: true, title: 'Selected problem location: drag to reposition',
            icon: L.divIcon({ className: 'incident-point incident-pin-icon', html: '<span aria-hidden="true"></span>', iconSize: [28, 36], iconAnchor: [14, 36] }),
        }).addTo(map);
        point.on('dragstart', () => { gpsRequest++; candidate = null; confirmGps.hidden = true; gps.disabled = false; });
        point.on('dragend', () => update(point.getLatLng().wrap()));
        update(location, metres);
        if (metres === null) gpsStatus.textContent = 'Manual problem-location pin selected. GPS accuracy does not apply to this point.';
    };
    map.on('click', event => select(event.latlng.wrap()));
    center.disabled = false; center.addEventListener('click', () => select(map.getCenter().wrap()));
    clear.addEventListener('click', () => {
        gpsRequest++; candidate = null; confirmGps.hidden = true; gps.disabled = false;
        if (point) map.removeLayer(point); if (accuracyCircle) map.removeLayer(accuracyCircle);
        point = accuracyCircle = null; latitude.value = longitude.value = accuracy.value = '';
        coordinates.textContent = 'No coordinates selected.'; status.textContent = 'No incident location selected.'; clear.disabled = true;
    });
    document.getElementById('water_body_id').addEventListener('change', event => {
        const lake = mapped.find(item => String(item.id) === event.target.value);
        if (lake) map.setView([Number(lake.latitude), Number(lake.longitude)], 15, { animate: false });
    });
    gps.addEventListener('click', () => {
        if (!window.isSecureContext) { gpsStatus.textContent = 'GPS requires HTTPS or localhost. You can still place the pin manually.'; return; }
        if (!navigator.geolocation) { gpsStatus.textContent = 'GPS is unavailable. Tap the map or use its center to place the problem pin.'; return; }
        const request = ++gpsRequest; gps.disabled = true; confirmGps.hidden = true;
        gpsStatus.textContent = 'Requesting your location. Your current location is not automatically the problem location.';
        navigator.geolocation.getCurrentPosition(position => {
            if (request !== gpsRequest) return;
            gps.disabled = false;
            const { latitude: lat, longitude: lng, accuracy: metres } = position.coords;
            if (!valid(lat, lng) || !Number.isFinite(metres) || metres < 0 || metres > 100000) { gpsStatus.textContent = 'GPS returned unusable coordinates. Place the pin manually.'; return; }
            candidate = { location: L.latLng(lat, lng), accuracy: metres };
            if (accuracyCircle) map.removeLayer(accuracyCircle);
            accuracyCircle = L.circle(candidate.location, { radius: Math.max(metres, 1), color: '#277e96', fillOpacity: 0.08, interactive: false }).addTo(map);
            map.setView(candidate.location, 16, { animate: false });
            gpsStatus.textContent = 'Device accuracy: about ' + Math.round(metres) + ' metres. Confirm only if this is where you observed the problem; otherwise place the pin manually.';
            confirmGps.hidden = false;
        }, error => {
            if (request !== gpsRequest) return;
            gps.disabled = false;
            gpsStatus.textContent = (error.code === 1 ? 'Location permission denied.' : 'GPS is unavailable or timed out.') + ' Manual pin placement remains available.';
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
    });
    confirmGps.addEventListener('click', () => { if (candidate) select(candidate.location, candidate.accuracy); });
    if (valid(latitude.value, longitude.value)) {
        const restored = L.latLng(Number(latitude.value), Number(longitude.value));
        select(restored, accuracy.value === '' ? null : Number(accuracy.value));
        map.setView(restored, 15, { animate: false });
    }
    new ResizeObserver(() => map.invalidateSize()).observe(element);
}
const feedback = document.getElementById('form-errors') || document.getElementById('report-success') || document.getElementById('review-success');
if (feedback) feedback.focus();
