import './bootstrap';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

L.Icon.Default.mergeOptions({ iconRetinaUrl: markerIcon2x, iconUrl: markerIcon, shadowUrl: markerShadow });

document.addEventListener('DOMContentLoaded', () => {
    initEntrance();
    initSidebar();
    initUserMenu();
    initLoginPage();
    initSpatialDatasetForm();
    initMapPage();
});

// Give the first few cards/panels on a page a short staggered entrance
// so the layout settles instead of snapping into place.
function initEntrance() {
    const targets = document.querySelectorAll('[data-enter]');
    targets.forEach((el, index) => {
        el.classList.add('enter-surface');
        if (index < 4) el.classList.add(`enter-delay-${index + 1}`);
    });
}

function initSidebar() {
    const toggle = document.getElementById('sidebar-toggle');
    const close = document.getElementById('sidebar-close');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (!sidebar) return;
    const closeSidebar = () => {
        sidebar.classList.add('translate-x-full');
        overlay?.classList.add('opacity-0');
        toggle?.setAttribute('aria-expanded', 'false');
        // Wait for the fade before removing it from the layout.
        window.setTimeout(() => overlay?.classList.add('hidden'), 220);
    };
    const openSidebar = () => {
        overlay?.classList.remove('hidden');
        // Next frame, so the browser paints opacity-0 before the transition starts.
        window.requestAnimationFrame(() => overlay?.classList.remove('opacity-0'));
        sidebar.classList.remove('translate-x-full');
        toggle?.setAttribute('aria-expanded', 'true');
    };
    toggle?.addEventListener('click', openSidebar);
    close?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeSidebar(); });
}

function initUserMenu() {
    const toggle = document.getElementById('user-menu-toggle');
    const menu = document.getElementById('user-menu');
    const container = document.getElementById('user-menu-container');
    if (!toggle || !menu || !container) return;
    const chevron = document.getElementById('user-menu-chevron');
    const closeMenu = () => {
        menu.classList.add('hidden');
        menu.classList.remove('enter-menu');
        chevron?.classList.remove('rotate-180');
        toggle.setAttribute('aria-expanded', 'false');
    };
    const openMenu = () => {
        menu.classList.remove('hidden');
        menu.classList.add('enter-menu');
        chevron?.classList.add('rotate-180');
        toggle.setAttribute('aria-expanded', 'true');
    };
    toggle.addEventListener('click', () => {
        menu.classList.contains('hidden') ? openMenu() : closeMenu();
    });
    document.addEventListener('click', (event) => {
        if (!container.contains(event.target)) closeMenu();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMenu();
    });
}

function initLoginPage() {
    const form = document.getElementById('login-form');
    const password = document.getElementById('password');
    const toggle = document.getElementById('toggle-password');
    const openIcon = document.getElementById('eye-open');
    const closedIcon = document.getElementById('eye-closed');
    const button = document.getElementById('login-button');
    const text = document.getElementById('button-text');
    const spinner = document.getElementById('button-spinner');
    if (!form || !password || !toggle || !button) return;
    toggle.addEventListener('click', () => {
        const showing = password.type === 'password';
        password.type = showing ? 'text' : 'password';
        openIcon?.classList.toggle('hidden', showing);
        closedIcon?.classList.toggle('hidden', !showing);
        toggle.setAttribute('aria-pressed', showing ? 'true' : 'false');
    });
    form.addEventListener('submit', () => { button.disabled = true; text?.classList.add('hidden'); spinner?.classList.remove('hidden'); });
}

function initSpatialDatasetForm() {
    const checkbox = document.querySelector('[data-spatial-toggle]');
    const fields = document.getElementById('spatial-fields');
    const geometry = document.getElementById('geometry_type');
    const srid = document.getElementById('srid');
    if (!checkbox || !fields || !geometry || !srid) return;
    const sync = () => { const enabled = checkbox.checked; fields.classList.toggle('hidden', !enabled); geometry.required = enabled; srid.required = enabled; };
    checkbox.addEventListener('change', sync);
    sync();
}

function initMapPage() {
    const mapElement = document.getElementById('map');
    if (!mapElement) return;
    const strings = {
        loadFailed: mapElement.dataset.msgLoadFailed || '',
        featureDetails: mapElement.dataset.msgFeatureDetails || '',
    };
    const map = L.map(mapElement, { center: [31.5, 34.5], zoom: 8, zoomControl: true, attributionControl: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' }).addTo(map);
    const layers = {};
    const toggles = document.querySelectorAll('.layer-toggle');
    toggles.forEach(toggle => { if (toggle.checked) loadLayer(toggle.dataset.datasetId, toggle.id); toggle.addEventListener('change', () => toggle.checked ? loadLayer(toggle.dataset.datasetId, toggle.id) : removeLayer(toggle.dataset.datasetId)); });

    function loadLayer(datasetId, toggleId) {
        if (layers[datasetId]) return;
        const toggle = document.getElementById(toggleId);
        const item = toggle?.closest('.layer-item');
        toggle?.setAttribute('disabled', 'disabled');
        item?.classList.add('layer-loading');
        fetch(`/api/datasets/${datasetId}/features`)
            .then(response => { if (!response.ok) throw new Error(strings.loadFailed); return response.json(); })
            .then(data => {
                const geojsonLayer = L.geoJSON(data.features, { onEachFeature, pointToLayer, style: feature => styleFor(feature.geometry?.type) });
                layers[datasetId] = geojsonLayer;
                geojsonLayer.addTo(map);
                toggle?.removeAttribute('disabled');
                item?.classList.remove('layer-loading', 'layer-error');
                if (geojsonLayer.getLayers().length && Object.keys(layers).length === 1) map.fitBounds(geojsonLayer.getBounds(), { padding: [40, 40] });
            })
            .catch(error => { toggle?.removeAttribute('disabled'); if (toggle) toggle.checked = false; item?.classList.remove('layer-loading'); item?.classList.add('layer-error'); item?.setAttribute('data-error', error.message); });
    }
    function removeLayer(datasetId) { if (layers[datasetId]) { map.removeLayer(layers[datasetId]); delete layers[datasetId]; } }
    function pointToLayer(feature, latlng) { return L.circleMarker(latlng, { radius: 6, color: '#8B1A1A', weight: 1.5, fillColor: '#8B1A1A', fillOpacity: 0.65 }); }
    function styleFor(type) { const base = { color: '#8B1A1A', weight: 2, fillColor: '#8B1A1A', fillOpacity: 0.15 }; if (type?.includes('Line')) return { ...base, fillOpacity: 0 }; return base; }
    function onEachFeature(feature, layer) {
        if (!feature.properties) return;
        let html = `<div class="feature-popup"><strong>${escapeHtml(strings.featureDetails)}</strong>`;
        Object.entries(feature.properties).forEach(([key, value]) => { if (value !== null && value !== undefined) html += `<div class="property-row"><span class="property-key">${escapeHtml(key)}</span><span class="property-value">${escapeHtml(typeof value === 'object' ? JSON.stringify(value) : String(value))}</span></div>`; });
        html += '</div>'; layer.bindPopup(html, { maxWidth: 320 });
    }
    function escapeHtml(value) { const div = document.createElement('div'); div.textContent = value; return div.innerHTML; }
    document.getElementById('zoom-to-layers')?.addEventListener('click', () => { const active = Object.values(layers); if (active.length) map.fitBounds(L.featureGroup(active).getBounds(), { padding: [40, 40] }); });
    document.getElementById('reset-view')?.addEventListener('click', () => map.setView([31.5, 34.5], 8));
    setTimeout(() => map.invalidateSize(), 100);
}
