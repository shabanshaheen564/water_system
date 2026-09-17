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
        window.setTimeout(() => overlay?.classList.add('hidden'), 220);
    };
    const openSidebar = () => {
        overlay?.classList.remove('hidden');
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
    toggle.addEventListener('click', () => menu.classList.contains('hidden') ? openMenu() : closeMenu());
    document.addEventListener('click', (event) => { if (!container.contains(event.target)) closeMenu(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeMenu(); });
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
    if (mapElement.dataset.operationalMap !== 'true') return;

    const map = L.map(mapElement, { center: [31.5, 34.5], zoom: 10, zoomControl: true, attributionControl: true });
    const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    });
    const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        attribution: 'Tiles &copy; Esri'
    });
    osm.addTo(map);
    L.control.layers({ 'خريطة الشوارع': osm, 'صورة جوية / ستالايت': satellite }, {}, { position: 'topright', collapsed: false }).addTo(map);
    L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);

    const state = { complaints: [], tasks: [], datasets: [], complaintLayer: L.layerGroup().addTo(map), taskLayer: L.layerGroup().addTo(map), datasetLayers: {}, filtered: { complaints: [], tasks: [] } };
    const strings = { loadFailed: mapElement.dataset.msgLoadFailed || 'تعذر تحميل بيانات الخريطة.' };
    const dataUrl = mapElement.dataset.mapDataUrl;
    const labels = {
        complaintStatus: { open: 'مفتوحة', in_progress: 'قيد المعالجة', resolved: 'تم الحل', closed: 'مغلقة', cancelled: 'ملغاة' },
        taskStatus: { pending: 'معلقة', assigned: 'مسندة', in_progress: 'قيد التنفيذ', completed: 'مكتملة', cancelled: 'ملغاة' },
        priority: { urgent: 'عاجلة', high: 'عالية', medium: 'متوسطة', low: 'منخفضة' }
    };

    const escapeHtml = (value) => { const div = document.createElement('div'); div.textContent = value ?? ''; return div.innerHTML; };
    const markerIconFor = (type) => L.divIcon({ className: '', html: `<div class="map-marker ${type}">${type === 'complaint' ? '!' : '✓'}</div>`, iconSize: [30, 30], iconAnchor: [15, 15], popupAnchor: [0, -16] });

    function popupHtml(item, type) {
        const isComplaint = type === 'complaint';
        const statusLabel = isComplaint ? labels.complaintStatus[item.status] : labels.taskStatus[item.status];
        const priorityLabel = labels.priority[item.priority] || item.priority || '—';
        const related = isComplaint
            ? (item.work_orders || []).map(work => `<div class="row"><span class="key">المهمة</span><span class="value">${escapeHtml(work.number)} · ${escapeHtml(labels.taskStatus[work.status] || work.status)}</span></div>`).join('')
            : `<div class="row"><span class="key">الشكاوى المرتبطة</span><span class="value">${escapeHtml(item.complaints_count)}</span></div>`;
        return `<div class="map-popup"><h4>${escapeHtml(isComplaint ? item.number : item.number)} — ${escapeHtml(item.title)}</h4><div class="row"><span class="key">الحالة</span><span class="value">${escapeHtml(statusLabel || item.status)}</span></div><div class="row"><span class="key">الأولوية</span><span class="value">${escapeHtml(priorityLabel)}</span></div><div class="row"><span class="key">المسؤول</span><span class="value">${escapeHtml(item.assigned_to || 'غير مسند')}</span></div>${isComplaint && item.contact_name ? `<div class="row"><span class="key">المواطن</span><span class="value">${escapeHtml(item.contact_name)}</span></div>` : ''}${isComplaint && item.address ? `<div class="row"><span class="key">العنوان</span><span class="value">${escapeHtml(item.address)}</span></div>` : ''}${related}<div style="margin-top:10px"><a href="${escapeHtml(item.url)}" class="text-brand-600 font-medium">عرض التفاصيل ←</a></div></div>`;
    }

    function renderOperationalLayers() {
        state.complaintLayer.clearLayers();
        state.taskLayer.clearLayers();
        state.filtered.complaints.forEach(item => {
            const marker = L.marker([item.latitude, item.longitude], { icon: markerIconFor('complaint'), title: item.number });
            marker.bindPopup(popupHtml(item, 'complaint'), { maxWidth: 360 });
            state.complaintLayer.addLayer(marker);
        });
        state.filtered.tasks.forEach(item => {
            const marker = L.marker([item.latitude, item.longitude], { icon: markerIconFor('task'), title: item.number });
            marker.bindPopup(popupHtml(item, 'task'), { maxWidth: 360 });
            state.taskLayer.addLayer(marker);
        });
        updateStats();
    }

    function updateStats() {
        document.getElementById('stat-complaints')?.replaceChildren(document.createTextNode(String(state.filtered.complaints.length)));
        document.getElementById('stat-tasks')?.replaceChildren(document.createTextNode(String(state.filtered.tasks.length)));
        const high = [...state.filtered.complaints, ...state.filtered.tasks].filter(item => ['high', 'urgent'].includes(item.priority)).length;
        document.getElementById('stat-high')?.replaceChildren(document.createTextNode(String(high)));
        document.getElementById('stat-datasets')?.replaceChildren(document.createTextNode(String(state.datasets.length)));
    }

    function applyFilters() {
        const search = (document.getElementById('map-search')?.value || '').trim().toLowerCase();
        const status = document.getElementById('map-status')?.value || '';
        const priority = document.getElementById('map-priority')?.value || '';
        const matches = (item) => {
            const text = [item.number, item.title, item.description, item.contact_name, item.address, item.assigned_to].filter(Boolean).join(' ').toLowerCase();
            return (!search || text.includes(search)) && (!status || item.status === status) && (!priority || item.priority === priority);
        };
        state.filtered.complaints = state.complaints.filter(matches);
        state.filtered.tasks = state.tasks.filter(matches);
        renderOperationalLayers();
    }

    function populateStatusFilter() {
        const select = document.getElementById('map-status');
        if (!select) return;
        const values = new Set([...state.complaints.map(item => item.status), ...state.tasks.map(item => item.status)]);
        [...values].forEach(value => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = labels.complaintStatus[value] || labels.taskStatus[value] || value;
            select.appendChild(option);
        });
    }

    function loadDataset(datasetId, checkbox) {
        if (state.datasetLayers[datasetId]) return;
        checkbox.disabled = true;
        fetch(`/api/datasets/${datasetId}/features`)
            .then(response => { if (!response.ok) throw new Error(strings.loadFailed); return response.json(); })
            .then(data => {
                const layer = L.geoJSON(data.features || [], {
                    pointToLayer: (_, latlng) => L.circleMarker(latlng, { radius: 6, color: '#475467', weight: 1.5, fillColor: '#667085', fillOpacity: .7 }),
                    style: () => ({ color: '#475467', weight: 2, fillColor: '#98A2B3', fillOpacity: .18 }),
                    onEachFeature: (feature, featureLayer) => {
                        const rows = Object.entries(feature.properties || {}).filter(([, value]) => value !== null && value !== '').map(([key, value]) => `<div class="row"><span class="key">${escapeHtml(key)}</span><span class="value">${escapeHtml(typeof value === 'object' ? JSON.stringify(value) : String(value))}</span></div>`).join('');
                        featureLayer.bindPopup(`<div class="map-popup"><h4>تفاصيل المعلم</h4>${rows}</div>`, { maxWidth: 380 });
                    }
                }).addTo(map);
                state.datasetLayers[datasetId] = layer;
                checkbox.disabled = false;
                if (layer.getLayers().length) map.fitBounds(layer.getBounds(), { padding: [35, 35], maxZoom: 16 });
            })
            .catch(() => { checkbox.checked = false; checkbox.disabled = false; });
    }

    function removeDataset(datasetId) {
        const layer = state.datasetLayers[datasetId];
        if (layer) { map.removeLayer(layer); delete state.datasetLayers[datasetId]; }
    }

    function fitVisible() {
        const layers = [];
        if (document.getElementById('toggle-complaints')?.checked && state.complaintLayer.getLayers().length) layers.push(state.complaintLayer);
        if (document.getElementById('toggle-tasks')?.checked && state.taskLayer.getLayers().length) layers.push(state.taskLayer);
        Object.values(state.datasetLayers).forEach(layer => { if (map.hasLayer(layer) && layer.getLayers().length) layers.push(layer); });
        if (layers.length) map.fitBounds(L.featureGroup(layers).getBounds(), { padding: [45, 45], maxZoom: 16 });
    }

    document.getElementById('toggle-complaints')?.addEventListener('change', (event) => event.target.checked ? map.addLayer(state.complaintLayer) : map.removeLayer(state.complaintLayer));
    document.getElementById('toggle-tasks')?.addEventListener('change', (event) => event.target.checked ? map.addLayer(state.taskLayer) : map.removeLayer(state.taskLayer));
    document.querySelectorAll('.dataset-toggle').forEach(checkbox => checkbox.addEventListener('change', (event) => event.target.checked ? loadDataset(event.target.dataset.datasetId, event.target) : removeDataset(event.target.dataset.datasetId)));
    document.getElementById('toggle-map-filter')?.addEventListener('click', () => document.getElementById('map-filter')?.classList.toggle('open'));
    document.getElementById('map-search')?.addEventListener('input', applyFilters);
    document.getElementById('map-status')?.addEventListener('change', applyFilters);
    document.getElementById('map-priority')?.addEventListener('change', applyFilters);
    document.getElementById('clear-map-filter')?.addEventListener('click', () => { document.getElementById('map-search').value = ''; document.getElementById('map-status').value = ''; document.getElementById('map-priority').value = ''; applyFilters(); });
    document.getElementById('zoom-to-visible')?.addEventListener('click', fitVisible);
    document.getElementById('reset-map')?.addEventListener('click', () => map.setView([31.5, 34.5], 10));

    fetch(dataUrl)
        .then(response => { if (!response.ok) throw new Error(strings.loadFailed); return response.json(); })
        .then(data => {
            state.complaints = data.complaints || [];
            state.tasks = data.work_orders || [];
            state.datasets = data.datasets || [];
            state.filtered.complaints = [...state.complaints];
            state.filtered.tasks = [...state.tasks];
            populateStatusFilter();
            renderOperationalLayers();
            fitVisible();
        })
        .catch(() => {
            document.getElementById('stat-complaints')?.replaceChildren(document.createTextNode('—'));
            document.getElementById('stat-tasks')?.replaceChildren(document.createTextNode('—'));
        });

    window.setTimeout(() => map.invalidateSize(), 150);
}
