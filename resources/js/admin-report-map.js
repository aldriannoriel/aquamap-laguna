import L from 'leaflet';
const element = document.getElementById('private-report-map');
if (element) {
    const lat = Number(element.dataset.latitude), lng = Number(element.dataset.longitude);
    if (Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) {
        const map = L.map(element, { scrollWheelZoom: false }).setView([lat, lng], 16);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' }).addTo(map);
        L.marker([lat, lng], { icon: L.divIcon({ className: 'incident-pin-icon private-incident-point', html: '<span aria-hidden="true"></span>', iconSize: [28, 36], iconAnchor: [14, 36] }) }).bindTooltip('Reporter-selected problem location / unverified').addTo(map);
        new ResizeObserver(() => map.invalidateSize()).observe(element);
    }
}
