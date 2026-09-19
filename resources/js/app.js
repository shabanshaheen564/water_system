import './bootstrap';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

L.Icon.Default.mergeOptions({ iconRetinaUrl: markerIcon2x, iconUrl: markerIcon, shadowUrl: markerShadow });

const DEIR_AL_BALAH_CENTER = [31.417, 34.368];
const CENTRAL_GAZA_VIEWBOX = '34.27,31.56,34.56,31.36';

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

async function searchCentralGaza(query) {
    const encoded = encodeURIComponent(query);
    const nominatimUrl = `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=50&addressdetails=1&accept-language=ar&countrycodes=ps&viewbox=${CENTRAL_GAZA_VIEWBOX}&bounded=1&q=${encoded}`;
    const photonUrl = `https://photon.komoot.io/api/?q=${encoded}&limit=50&lang=ar&bbox=34.28,31.36,34.58,31.56`;

    try {
        const response = await fetch(nominatimUrl, { headers: { 'Accept': 'application/json' } });
        if (response.ok) {
            const results = await response.json();
            const filteredResults = (Array.isArray(results) ? results : []).filter(result => {
                const lat = Number(result.lat);
                const lon = Number(result.lon);
                return lat >= 31.36 && lat <= 31.56 && lon >= 34.27 && lon <= 34.56;
            });
            if (filteredResults.length) return filteredResults;
        }
    } catch {}

    const fallback = await fetch(photonUrl, { headers: { 'Accept': 'application/json' } });
    if (!fallback.ok) throw new Error('search_failed');
    const data = await fallback.json();
    return (data.features || []).map(feature => ({
        name: feature.properties?.name || feature.properties?.street || feature.properties?.city || 'نتيجة',
        display_name: [
            feature.properties?.name,
            feature.properties?.street,
            feature.properties?.district,
            feature.properties?.city,
            feature.properties?.state
        ].filter(Boolean).filter((value, index, values) => values.indexOf(value) === index).join('، '),
        lat: feature.geometry?.coordinates?.[1],
        lon: feature.geometry?.coordinates?.[0],
    })).filter(result => Number.isFinite(Number(result.lat)) && Number.isFinite(Number(result.lon)));
}



document.addEventListener('DOMContentLoaded', () => {
    initEntrance();
    initSidebar();
    initUserMenu();
    initLoginPage();
    initSpatialDatasetForm();
    initLocationPickers();
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

function initLocationPickers() {
    const buttons = document.querySelectorAll('[data-location-picker]');
    if (!buttons.length) return;

    let modalMap = null;
    let modalMarker = null;
    let activeButton = null;

    const modal = document.createElement('div');
    modal.id = 'location-picker-modal';
    modal.className = 'fixed inset-0 z-[2000] hidden items-center justify-center bg-black/50 p-4';
    modal.innerHTML = `
        <div class="flex h-[min(760px,92vh)] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl" dir="rtl">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
                <div>
                    <h3 class="text-base font-semibold text-ink">تحديد الموقع على الخريطة</h3>
                    <p class="text-xs text-ink-secondary">ابحث عن مكان ثم اضغط على الخريطة لتحديد النقطة.</p>
                </div>
                <button type="button" data-location-close class="rounded-md border border-border-strong bg-white px-3 py-1.5 text-sm text-ink">إغلاق</button>
            </div>
            <div class="flex flex-wrap gap-2 border-b border-border bg-surface-1 p-3">
                <input data-location-search type="search" placeholder="ابحث عن مكان، شارع، حي..." class="min-w-0 flex-1 rounded-md border border-border-strong bg-white px-3 py-2 text-sm">
                <button type="button" data-location-search-button class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white">بحث</button>
                <span data-location-status class="flex items-center text-xs text-ink-secondary"></span>
            </div>
            <div class="relative min-h-0 flex-1"><div data-location-map class="h-full min-h-0"></div><div data-location-results class="absolute bottom-4 end-4 z-[2100] max-h-80 w-[min(460px,calc(100%-2rem))] overflow-y-auto rounded-md border border-border bg-white shadow-lg"></div></div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3">
                <div data-location-coordinates class="text-xs text-ink-secondary">لم يتم تحديد موقع بعد.</div>
                <button type="button" data-location-confirm disabled class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50">اعتماد الموقع</button>
            </div>
        </div>`;
    document.body.appendChild(modal);

    const mapHost = modal.querySelector('[data-location-map]');
    const searchInput = modal.querySelector('[data-location-search]');
    const searchButton = modal.querySelector('[data-location-search-button]');
    const status = modal.querySelector('[data-location-status]');
    const coordinates = modal.querySelector('[data-location-coordinates]');
    const confirmButton = modal.querySelector('[data-location-confirm]');

    const setMarker = (lat, lng, zoom = 17) => {
        if (!modalMap) return;
        const point = L.latLng(Number(lat), Number(lng));
        if (modalMarker) modalMarker.setLatLng(point);
        else modalMarker = L.marker(point).addTo(modalMap);
        modalMap.setView(point, Math.max(modalMap.getZoom(), zoom));
        coordinates.textContent = `خط العرض: ${point.lat.toFixed(6)} — خط الطول: ${point.lng.toFixed(6)}`;
        confirmButton.disabled = false;
        activeButton.dataset.selectedLat = String(point.lat);
        activeButton.dataset.selectedLng = String(point.lng);
    };

    const renderSearchResults = (results, listElement, onSelect) => {
        listElement.innerHTML = '';
        results.forEach(result => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'block w-full border-b border-border px-3 py-2 text-start text-xs hover:bg-surface-1 last:border-b-0';
            item.innerHTML = '<strong class="block text-ink">' + escapeHtml(result.name || result.display_name || 'نتيجة') + '</strong><span class="mt-0.5 block text-ink-secondary">' + escapeHtml(result.display_name || '') + '</span>';
            item.addEventListener('click', () => onSelect(result));
            listElement.appendChild(item);
        });
    };

    const searchPlaces = async () => {
        const query = searchInput.value.trim();
        if (!query) return;
        status.textContent = 'جاري البحث...';
        const resultsList = modal.querySelector('[data-location-results]');
        resultsList.innerHTML = '';
        try {
            const results = await searchCentralGaza(query);
            if (!results.length) { status.textContent = 'لم يتم العثور على نتائج داخل محافظة الوسطى.'; return; }
            status.textContent = `تم العثور على ${results.length} نتيجة داخل محافظة الوسطى — اختر الموقع المطلوب.`;
            renderSearchResults(results, resultsList, result => {
                setMarker(result.lat, result.lon, 17);
                status.textContent = result.display_name || 'تم اختيار الموقع.';
                resultsList.innerHTML = '';
            });
        } catch {
            status.textContent = 'تعذر تنفيذ البحث حالياً.';
        }
    };

    const open = (button) => {
        activeButton = button;
        const latField = document.getElementById(button.dataset.latitudeField);
        const lngField = document.getElementById(button.dataset.longitudeField);
        const initialLat = latField?.value?.trim() !== '' ? Number(latField.value) : NaN;
        const initialLng = lngField?.value?.trim() !== '' ? Number(lngField.value) : NaN;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');

        if (!modalMap) {
            modalMap = L.map(mapHost, { zoomControl: true, attributionControl: true }).setView(DEIR_AL_BALAH_CENTER, 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(modalMap);
            modalMap.on('click', event => setMarker(event.latlng.lat, event.latlng.lng, 17));
        }
        window.setTimeout(() => {
            modalMap.invalidateSize();
            if (Number.isFinite(initialLat) && Number.isFinite(initialLng)) setMarker(initialLat, initialLng, 17);
            else if (activeButton.dataset.selectedLat && activeButton.dataset.selectedLng) setMarker(activeButton.dataset.selectedLat, activeButton.dataset.selectedLng, 17);
        }, 80);
        searchInput.value = '';
        status.textContent = '';
        searchInput.focus();
    };

    const close = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
        activeButton = null;
    };

    modal.querySelector('[data-location-close]').addEventListener('click', close);
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    searchButton.addEventListener('click', searchPlaces);
    searchInput.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); searchPlaces(); } });
    confirmButton.addEventListener('click', () => {
        if (!activeButton) return;
        const latField = document.getElementById(activeButton.dataset.latitudeField);
        const lngField = document.getElementById(activeButton.dataset.longitudeField);
        if (latField && lngField && activeButton.dataset.selectedLat && activeButton.dataset.selectedLng) {
            latField.value = Number(activeButton.dataset.selectedLat).toFixed(6);
            lngField.value = Number(activeButton.dataset.selectedLng).toFixed(6);
            latField.dispatchEvent(new Event('input', { bubbles: true }));
            lngField.dispatchEvent(new Event('input', { bubbles: true }));
        }
        close();
    });

    buttons.forEach(button => button.addEventListener('click', () => open(button)));
}

function initMapPage() {
    const mapElement = document.getElementById('map');
    if (!mapElement) return;
    if (mapElement.dataset.operationalMap !== 'true') return;

    const map = L.map(mapElement, { center: [31.5, 34.5], zoom: 10, zoomControl: false, attributionControl: true });
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
    L.control.zoom({ position: 'bottomright' }).addTo(map);
    L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);

    const state = { complaints: [], tasks: [], datasets: [], complaintLayer: L.layerGroup().addTo(map), taskLayer: L.layerGroup().addTo(map), datasetLayers: {}, filtered: { complaints: [], tasks: [] } };
    const strings = { loadFailed: mapElement.dataset.msgLoadFailed || 'تعذر تحميل بيانات الخريطة.' };
    const dataUrl = mapElement.dataset.mapDataUrl;
    const labels = {
        complaintStatus: { open: 'مفتوحة', in_progress: 'قيد المعالجة', resolved: 'تم الحل', closed: 'مغلقة', cancelled: 'ملغاة' },
        taskStatus: { pending: 'معلقة', assigned: 'مسندة', in_progress: 'قيد التنفيذ', completed: 'مكتملة', cancelled: 'ملغاة' },
        priority: { urgent: 'عاجلة', high: 'عالية', medium: 'متوسطة', low: 'منخفضة' }
    };

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

    let placeSearchMarker = null;

    async function searchMapPlace() {
        const input = document.getElementById('map-place-search');
        const status = document.getElementById('map-place-search-status');
        const resultsList = document.getElementById('map-place-search-results');
        const query = input?.value.trim();
        if (!query) return;

        status.textContent = 'جاري البحث داخل محافظة الوسطى...';
        resultsList.innerHTML = '';

        try {
            const results = await searchCentralGaza(query);
            if (!results.length) {
                status.textContent = 'لم يتم العثور على نتائج داخل محافظة الوسطى.';
                return;
            }

            status.textContent = `تم العثور على ${results.length} نتيجة داخل محافظة الوسطى — اختر الموقع المطلوب.`;

            results.forEach(result => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'block w-full border-b border-border px-3 py-2 text-start text-xs hover:bg-surface-1 last:border-b-0';
                item.innerHTML = '<strong class="block text-ink">' + escapeHtml(result.name || result.display_name || 'نتيجة') + '</strong><span class="mt-0.5 block text-ink-secondary">' + escapeHtml(result.display_name || '') + '</span>';

                item.addEventListener('click', () => {
                    const lat = Number(result.lat);
                    const lng = Number(result.lon);
                    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

                    if (placeSearchMarker) map.removeLayer(placeSearchMarker);
                    placeSearchMarker = L.marker([lat, lng]).addTo(map);
                    placeSearchMarker.bindPopup('<div dir="rtl"><strong>' + escapeHtml(result.display_name || query) + '</strong><div class="mt-1 text-xs">خط العرض: ' + lat.toFixed(6) + '<br>خط الطول: ' + lng.toFixed(6) + '</div></div>').openPopup();
                    map.setView([lat, lng], 17);
                    status.textContent = result.display_name || 'تم اختيار الموقع.';
                });

                resultsList.appendChild(item);
            });
        } catch {
            status.textContent = 'تعذر تنفيذ البحث حالياً. حاول مرة أخرى.';
        }
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
    document.getElementById('map-place-search-button')?.addEventListener('click', searchMapPlace);
    document.getElementById('map-place-search')?.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); searchMapPlace(); } });
    document.getElementById('map-status')?.addEventListener('change', applyFilters);
    document.getElementById('map-priority')?.addEventListener('change', applyFilters);
    document.getElementById('clear-map-filter')?.addEventListener('click', () => { document.getElementById('map-search').value = ''; document.getElementById('map-status').value = ''; document.getElementById('map-priority').value = ''; applyFilters(); });
    document.getElementById('zoom-to-visible')?.addEventListener('click', fitVisible);
    document.getElementById('reset-map')?.addEventListener('click', () => map.setView(DEIR_AL_BALAH_CENTER, 13));

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
