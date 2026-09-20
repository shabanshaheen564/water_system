import './bootstrap';
import 'leaflet/dist/leaflet.css';
import 'leaflet-draw/dist/leaflet.draw.css';
import L from 'leaflet';
import 'leaflet-draw';
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

    const state = {
        complaints: [],
        tasks: [],
        datasets: [],
        complaintLayer: L.layerGroup().addTo(map),
        taskLayer: L.layerGroup().addTo(map),
        datasetLayers: {},
        filtered: { complaints: [], tasks: [] },
        drawing: { active: false, layer: null },
        editingFeature: null,
        analysisLayer: L.layerGroup().addTo(map),
        spatialPick: { active: false, marker: null, lat: null, lng: null },
        measurement: { active: false, type: null }
    };

    const drawingStatus = document.getElementById('gis-drawing-status');
    const drawingDataset = document.getElementById('gis-edit-dataset');
    const drawingButton = document.getElementById('gis-start-drawing');
    const drawnItems = new L.FeatureGroup().addTo(map);
    const attributeModal = document.getElementById('gis-attribute-modal');
    const attributeFields = document.getElementById('gis-attribute-fields');
    const attributeError = document.getElementById('gis-attribute-error');
    const attributeDatasetName = document.getElementById('gis-attribute-dataset-name');
    const attributeSave = document.getElementById('gis-attribute-save');
    let attributeFieldsData = [];

    const drawingGeometryType = () => drawingDataset?.selectedOptions?.[0]?.dataset.geometryType || '';

    const syncDrawingControls = () => {
        const type = drawingGeometryType();
        const supported = ['Point', 'LineString', 'Polygon'].includes(type);
        if (drawingButton) drawingButton.disabled = !supported || state.drawing.active;
        if (drawingStatus && type && !supported) drawingStatus.textContent = 'هذا النوع سيُدعم في مرحلة لاحقة: ' + type + '.';
        if (drawingStatus && !type) drawingStatus.textContent = 'اختر طبقة مكانية ثم ابدأ الرسم.';
        if (drawingStatus && supported && !state.drawing.active) drawingStatus.textContent = 'جاهز لرسم ' + type + '.';
    };

    const stopDrawing = () => {
        if (state.drawing.layer) {
            map.removeLayer(state.drawing.layer);
            state.drawing.layer = null;
        }
        drawnItems.clearLayers();
        state.drawing.active = false;
        syncDrawingControls();
    };

    const geometryCoordinatesFromLayer = (layer, type) => {
        if (type === 'Point') {
            const point = layer.getLatLng();
            return [point.lng, point.lat];
        }

        const convert = value => Array.isArray(value)
            ? value.map(convert)
            : [value.lng, value.lat];

        return convert(layer.getLatLngs());
    };

    const closeAttributeModal = () => {
        attributeModal?.classList.add('hidden');
        attributeModal?.classList.remove('flex');
        if (attributeError) {
            attributeError.textContent = '';
            attributeError.classList.add('hidden');
        }
    };

    const fieldInput = (field, existingValue = undefined) => {
        const required = field.is_required ? 'required' : '';
        const value = existingValue !== undefined ? existingValue : (field.default_value ?? '');
        const base = 'mt-1 w-full rounded-md border border-border-strong bg-white px-3 py-2 text-sm outline-none focus:border-brand-600';
        const label = '<label class="block text-xs font-medium text-ink">' + escapeHtml(field.display_name || field.name) + (field.is_required ? ' <span class="text-danger">*</span>' : '') + '</label>';

        if (field.data_type === 'text') {
            return label + '<textarea name="' + escapeHtml(field.name) + '" rows="3" class="' + base + '" ' + required + '>' + escapeHtml(value) + '</textarea>';
        }

        if (field.data_type === 'boolean') {
            return '<label class="flex items-center gap-2 text-xs font-medium text-ink"><input type="checkbox" name="' + escapeHtml(field.name) + '" value="1" class="h-4 w-4 rounded border-border-strong" ' + (value ? 'checked' : '') + '> ' + escapeHtml(field.display_name || field.name) + '</label>';
        }

        const type = field.data_type === 'integer' || field.data_type === 'decimal' ? 'number'
            : field.data_type === 'date' ? 'date'
            : field.data_type === 'datetime' ? 'datetime-local'
            : 'text';

        const step = field.data_type === 'decimal' ? ' step="any"' : '';
        return label + '<input name="' + escapeHtml(field.name) + '" type="' + type + '" value="' + escapeHtml(value) + '" class="' + base + '"' + step + ' ' + required + '>';
    };

    const openAttributeModal = async () => {
        const datasetId = drawingDataset?.value;
        if (!datasetId || !state.drawing.layer) return;

        const selected = drawingDataset.selectedOptions?.[0];
        if (attributeDatasetName) attributeDatasetName.textContent = selected?.textContent || '';
        if (attributeFields) attributeFields.innerHTML = '<p class="text-xs text-ink-secondary">جاري تحميل الحقول...</p>';
        attributeModal?.classList.remove('hidden');
        attributeModal?.classList.add('flex');

        try {
            const response = await fetch('/datasets/' + datasetId + '/fields/data');
            if (!response.ok) throw new Error('تعذر تحميل حقول الطبقة.');
            const data = await response.json();
            attributeFieldsData = data.data || [];
            attributeFields.innerHTML = attributeFieldsData.length
                ? attributeFieldsData.map(fieldInput).join('')
                : '<p class="text-xs text-ink-secondary">لا توجد حقول. سيتم حفظ المعلم بدون خصائص إضافية.</p>';
        } catch (error) {
            if (attributeFields) attributeFields.innerHTML = '';
            if (attributeError) {
                attributeError.textContent = error.message || 'تعذر تحميل حقول الطبقة.';
                attributeError.classList.remove('hidden');
            }
        }
    };

    const startDrawing = () => {
        const type = drawingGeometryType();
        if (!['Point', 'LineString', 'Polygon'].includes(type)) return;
        stopDrawing();
        const options = { shapeOptions: { color: '#475467', weight: 3, fillOpacity: 0.2 } };
        const handler = type === 'Point'
            ? new L.Draw.Marker(map)
            : type === 'LineString'
                ? new L.Draw.Polyline(map, options)
                : new L.Draw.Polygon(map, options);
        state.drawing.active = true;
        if (drawingStatus) drawingStatus.textContent = 'ارسم ' + type + ' على الخريطة. اضغط Esc للإلغاء.';
        syncDrawingControls();
        handler.enable();
    };

    map.on(L.Draw.Event.CREATED, event => {
        if (state.measurement.active) {
            handleMeasurementCreated(event);
            return;
        }

        const selectedType = drawingGeometryType();
        const drawnType = event.layerType === 'marker' ? 'Point' : event.layerType === 'polyline' ? 'LineString' : 'Polygon';
        if (selectedType !== drawnType) {
            if (drawingStatus) drawingStatus.textContent = 'نوع الرسم لا يطابق نوع الطبقة.';
            stopDrawing();
            return;
        }

        state.drawing.layer = event.layer;
        drawnItems.clearLayers();
        drawnItems.addLayer(event.layer);
        state.drawing.active = false;
        syncDrawingControls();

        if (drawingStatus) drawingStatus.textContent = 'تم إنشاء الشكل. أدخل الخصائص ثم اضغط حفظ المعلم.';
        const bounds = event.layer.getBounds ? event.layer.getBounds() : null;
        if (bounds?.isValid?.()) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 17 });
        else if (event.layer.getLatLng) map.setView(event.layer.getLatLng(), Math.max(map.getZoom(), 17));
        openAttributeModal();
    });

    drawingDataset?.addEventListener('change', () => { stopDrawing(); closeAttributeModal(); syncDrawingControls(); });
    drawingButton?.addEventListener('click', startDrawing);
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        if (state.measurement.active) {
            state.measurement.active = false;
            state.measurement.type = null;
            setGisToolsStatus('تم إلغاء القياس.');
        } else if (state.drawing.active) {
            stopDrawing();
        } else if (attributeModal && !attributeModal.classList.contains('hidden')) {
            closeAttributeModal();
        }
    });

    document.getElementById('gis-attribute-close')?.addEventListener('click', () => {
        if (state.editingFeature) cancelFeatureEdit();
        else closeAttributeModal();
    });
    document.getElementById('gis-attribute-cancel')?.addEventListener('click', () => {
        if (state.editingFeature) cancelFeatureEdit();
        else closeAttributeModal();
    });

    const cleanupFeatureEdit = () => {
        const editing = state.editingFeature;
        if (!editing) return;

        if (editing.editHandler?.enabled?.()) {
            editing.editHandler.disable();
        } else if (editing.editLayer?.editing?.enabled?.()) {
            editing.editLayer.editing.disable();
        }

        if (editing.pointMarker) {
            map.removeLayer(editing.pointMarker);
        }

        if (editing.locationControl) {
            map.removeControl(editing.locationControl);
            editing.locationControl = null;
        }

        if (editing.featureLayer && editing.originalStyle) {
            editing.featureLayer.setStyle(editing.originalStyle);
        }

        state.editingFeature = null;
    };

    const cloneLatLngs = (latLngs) => Array.isArray(latLngs)
        ? latLngs.map(item => Array.isArray(item) ? cloneLatLngs(item) : L.latLng(item.lat, item.lng))
        : [];

    const setFeatureLocationMode = (editing, enabled) => {
        if (!editing) return;

        if (enabled) {
            if (editing.geometryType === 'Point') {
                if (!editing.pointMarker) {
                    const latLng = editing.featureLayer.getLatLng();
                    editing.pointMarker = L.marker(latLng, {
                        draggable: true,
                        title: 'اسحب النقطة إلى الموقع الجديد'
                    }).addTo(map);

                    editing.pointMarker.on('dragstart', () => {
                        if (editing.locationStatus) {
                            editing.locationStatus.textContent = 'اسحب النقطة إلى الموقع المطلوب، ثم اضغط إنهاء تعديل الموقع.';
                        }
                    });

                    editing.pointMarker.on('dragend', () => {
                        const point = editing.pointMarker.getLatLng();
                        if (editing.locationStatus) {
                            editing.locationStatus.textContent = 'تم تحديد موقع جديد: ' + point.lat.toFixed(6) + '، ' + point.lng.toFixed(6);
                        }
                    });
                }

                editing.originalLatLngs = L.latLng(editing.featureLayer.getLatLng());
                editing.locationEditing = true;
                editing.editLayer = editing.pointMarker;
                editing.featureLayer.setStyle({ opacity: 0, fillOpacity: 0 });
                map.setView(editing.pointMarker.getLatLng(), Math.max(map.getZoom(), 17));
            } else if (['LineString', 'Polygon'].includes(editing.geometryType)) {
                editing.originalLatLngs = cloneLatLngs(editing.featureLayer.getLatLngs());
                editing.editHandler = new L.Edit.Poly(editing.featureLayer);
                editing.editHandler.enable();
                editing.locationEditing = true;
                editing.editLayer = editing.featureLayer;
                if (editing.locationStatus) {
                    editing.locationStatus.textContent = 'حرّك نقاط الشكل إلى الموقع المطلوب، ثم اضغط إنهاء تعديل الموقع.';
                }
            } else {
                return;
            }

            attributeModal?.classList.add('hidden');
            attributeModal?.classList.remove('flex');

            editing.locationControl = L.control({ position: 'topright' });
            editing.locationControl.onAdd = () => {
                const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                container.style.background = '#fff';
                container.style.padding = '10px';
                container.style.minWidth = '230px';
                container.style.direction = 'rtl';
                const locationText = editing.geometryType === 'Point'
                    ? 'اسحب النقطة إلى الموقع الجديد.'
                    : 'حرّك نقاط الشكل إلى الموقع الجديد.';
                container.innerHTML = `
                    <div style="font-size:12px;font-weight:600;margin-bottom:6px;">تعديل موقع المعلم</div>
                    <div style="font-size:11px;color:#667085;margin-bottom:8px;">${locationText}</div>
                    <button type="button" data-gis-finish-location style="width:100%;padding:7px 10px;border-radius:6px;background:#175cd3;color:#fff;font-size:12px;font-weight:600;">إنهاء تعديل الموقع</button>
                    <button type="button" data-gis-cancel-location style="width:100%;margin-top:5px;padding:7px 10px;border-radius:6px;border:1px solid #d0d5dd;background:#fff;color:#344054;font-size:12px;">إلغاء</button>
                `;

                L.DomEvent.disableClickPropagation(container);
                L.DomEvent.on(container.querySelector('[data-gis-finish-location]'), 'click', () => setFeatureLocationMode(editing, false));
                L.DomEvent.on(container.querySelector('[data-gis-cancel-location]'), 'click', () => {
                    if (editing.geometryType === 'Point') {
                        editing.pointMarker?.setLatLng(editing.originalLatLngs);
                    } else if (editing.originalLatLngs) {
                        editing.featureLayer.setLatLngs(cloneLatLngs(editing.originalLatLngs));
                    }
                    setFeatureLocationMode(editing, false);
                });
                return container;
            };
            editing.locationControl.addTo(map);
            return;
        }

        editing.locationEditing = false;
        editing.editLayer = editing.pointMarker || editing.featureLayer;

        if (editing.editHandler?.enabled?.()) {
            editing.editHandler.disable();
        }

        if (editing.locationControl) {
            map.removeControl(editing.locationControl);
            editing.locationControl = null;
        }

        if (editing.geometryType === 'Point') {
            editing.featureLayer.setLatLng(editing.pointMarker.getLatLng());
            editing.featureLayer.setStyle(editing.originalStyle);
        }

        attributeModal?.classList.remove('hidden');
        attributeModal?.classList.add('flex');
        window.setTimeout(() => map.invalidateSize(), 80);
    };

    const openFeatureEdit = async (datasetId, feature, featureLayer) => {
        if (mapElement.dataset.canEditGis !== '1') return;

        const checkbox = document.querySelector('.dataset-toggle[data-dataset-id="' + datasetId + '"]');
        if (!checkbox || checkbox.dataset.managementMode !== 'web_editable') return;

        cleanupFeatureEdit();

        const geometryType = feature.geometry?.type;
        if (!['Point', 'LineString', 'Polygon'].includes(geometryType)) {
            window.alert('هذا النوع من المعالم غير مدعوم للتحرير حالياً.');
            return;
        }

        const editing = {
            datasetId,
            featureId: feature.id,
            featureLayer,
            geometryType,
            editLayer: featureLayer,
            pointMarker: null,
            originalStyle: geometryType === 'Point'
                ? {
                    opacity: featureLayer.options.opacity ?? 1,
                    fillOpacity: featureLayer.options.fillOpacity ?? 0.5
                }
                : {
                    opacity: featureLayer.options.opacity ?? 1,
                    fillOpacity: featureLayer.options.fillOpacity ?? 0.2
                }
        };

        state.editingFeature = editing;

        if (geometryType === 'Point') {
            editing.editLayer = featureLayer;
            editing.locationEditing = false;
        } else {
            editing.locationEditing = false;
        }

        const selected = [...document.querySelectorAll('.dataset-toggle')].find(item => item.dataset.datasetId === String(datasetId));
        const datasetName = selected?.closest('label')?.querySelector('span')?.textContent?.trim() || 'الطبقة الجغرافية';
        if (attributeDatasetName) attributeDatasetName.textContent = datasetName;
        if (attributeFields) attributeFields.innerHTML = '<p class="text-xs text-ink-secondary">جاري تحميل الحقول...</p>';
        if (attributeError) {
            attributeError.textContent = '';
            attributeError.classList.add('hidden');
        }
        document.getElementById('gis-attribute-title')?.replaceChildren(document.createTextNode('تعديل المعلم'));
        if (attributeSave) attributeSave.textContent = 'حفظ التعديل';
        const locationButton = document.getElementById('gis-edit-location');
        const locationStatus = document.getElementById('gis-edit-location-status');
        editing.locationStatus = locationStatus;
        if (locationButton) {
            locationButton.disabled = !['Point', 'LineString', 'Polygon'].includes(geometryType);
            locationButton.textContent = 'تعديل الموقع';
            locationButton.onclick = () => setFeatureLocationMode(editing, true);
        }
        if (locationStatus) {
            locationStatus.textContent = 'يمكنك تعديل الموقع من الزر أعلاه.';
        }
        attributeModal?.classList.remove('hidden');
        attributeModal?.classList.add('flex');

        try {
            const response = await fetch('/datasets/' + datasetId + '/fields/data');
            if (!response.ok) throw new Error('تعذر تحميل حقول الطبقة.');
            const data = await response.json();
            attributeFieldsData = data.data || [];
            const existingValues = feature.properties || {};
            attributeFields.innerHTML = attributeFieldsData.length
                ? attributeFieldsData.map(field => fieldInput(field, existingValues[field.name])).join('')
                : '<p class="text-xs text-ink-secondary">لا توجد حقول إضافية لهذا المعلم.</p>';
        } catch (error) {
            cleanupFeatureEdit();
            closeAttributeModal();
            if (attributeError) {
                attributeError.textContent = error.message || 'تعذر تحميل حقول الطبقة.';
                attributeError.classList.remove('hidden');
            }
        }
    };

    const cancelFeatureEdit = () => {
        cleanupFeatureEdit();
        closeAttributeModal();
        document.getElementById('gis-attribute-title')?.replaceChildren(document.createTextNode('خصائص المعلم'));
        if (attributeSave) attributeSave.textContent = 'حفظ المعلم';
    };

    attributeSave?.addEventListener('click', async () => {
        if (state.editingFeature) {
            const editing = state.editingFeature;
            const values = {};
            attributeFieldsData.forEach(field => {
                const input = attributeFields?.querySelector('[name="' + CSS.escape(field.name) + '"]');
                if (!input) return;
                values[field.name] = field.data_type === 'boolean' ? input.checked : input.value;
            });

            attributeSave.disabled = true;
            if (attributeError) {
                attributeError.textContent = '';
                attributeError.classList.add('hidden');
            }

            try {
                const response = await fetch('/datasets/' + editing.datasetId + '/features/' + editing.featureId, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({
                        values,
                        geometry: {
                            type: editing.geometryType,
                            coordinates: geometryCoordinatesFromLayer(editing.editLayer, editing.geometryType),
                        },
                    }),
                });
                const data = await response.json();

                if (!response.ok) {
                    const messages = Object.values(data.errors || {}).flat();
                    throw new Error(messages.join(' ') || data.message || 'تعذر حفظ التعديل.');
                }

                const datasetId = editing.datasetId;
                const checkbox = document.querySelector('.dataset-toggle[data-dataset-id="' + datasetId + '"]');
                cleanupFeatureEdit();
                closeAttributeModal();
                document.getElementById('gis-attribute-title')?.replaceChildren(document.createTextNode('خصائص المعلم'));
                attributeSave.textContent = 'حفظ المعلم';

                if (checkbox?.checked) {
                    removeDataset(datasetId);
                    loadDataset(datasetId, checkbox);
                }
            } catch (error) {
                if (attributeError) {
                    attributeError.textContent = error.message || 'تعذر حفظ التعديل.';
                    attributeError.classList.remove('hidden');
                }
            } finally {
                attributeSave.disabled = false;
            }

            return;
        }

        const datasetId = drawingDataset?.value;
        const type = drawingGeometryType();
        const layer = state.drawing.layer;
        if (!datasetId || !layer) return;

        attributeSave.disabled = true;
        if (attributeError) {
            attributeError.textContent = '';
            attributeError.classList.add('hidden');
        }

        const values = {};
        attributeFieldsData.forEach(field => {
            const input = attributeFields?.querySelector('[name="' + CSS.escape(field.name) + '"]');
            if (!input) return;

            if (field.data_type === 'boolean') {
                values[field.name] = input.checked;
                return;
            }

            if (input.value !== '') values[field.name] = input.value;
        });

        const payload = {
            values,
            geometry: {
                type,
                coordinates: geometryCoordinatesFromLayer(layer, type),
            },
        };

        try {
            const response = await fetch('/datasets/' + datasetId + '/features', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json();

            if (!response.ok) {
                const messages = Object.values(data.errors || {}).flat();
                throw new Error(messages.join(' ') || data.message || 'تعذر حفظ المعلم.');
            }

            closeAttributeModal();
            stopDrawing();
            if (drawingStatus) drawingStatus.textContent = 'تم حفظ المعلم وخصائصه بنجاح.';
            const checkbox = document.querySelector('.dataset-toggle[data-dataset-id="' + datasetId + '"]');
            if (checkbox?.checked) {
                removeDataset(datasetId);
                loadDataset(datasetId, checkbox);
            }
        } catch (error) {
            if (attributeError) {
                attributeError.textContent = error.message || 'تعذر حفظ المعلم.';
                attributeError.classList.remove('hidden');
            }
        } finally {
            attributeSave.disabled = false;
        }
    });

    syncDrawingControls();

    const gisToolsDataset = document.getElementById('gis-query-dataset');
    const gisToolsField = document.getElementById('gis-query-field');
    const gisToolsOperator = document.getElementById('gis-query-operator');
    const gisToolsValue = document.getElementById('gis-query-value');
    const gisToolsStatus = document.getElementById('gis-tools-status');
    const gisRadius = document.getElementById('gis-radius');
    const gisRadiusPick = document.getElementById('gis-radius-pick');
    const gisRadiusSearch = document.getElementById('gis-radius-search');
    const gisNearestSearch = document.getElementById('gis-nearest-search');
    const gisBboxSearch = document.getElementById('gis-bbox-search');
    const gisQuerySubmit = document.getElementById('gis-query-submit');
    const gisMeasureDistance = document.getElementById('gis-measure-distance');
    const gisMeasureArea = document.getElementById('gis-measure-area');

    const setGisToolsStatus = (message) => {
        if (gisToolsStatus) gisToolsStatus.textContent = message || '';
    };

    const clearAnalysis = () => {
        state.analysisLayer.clearLayers();
        setGisToolsStatus('');
    };

    const renderAnalysisResults = (data, fit = true) => {
        state.analysisLayer.clearLayers();
        const features = Array.isArray(data.features) ? data.features : [];
        const layer = L.geoJSON(features, {
            pointToLayer: (_, latlng) => L.circleMarker(latlng, {
                radius: 8,
                color: '#175cd3',
                weight: 2,
                fillColor: '#175cd3',
                fillOpacity: 0.35,
            }),
            style: () => ({
                color: '#175cd3',
                weight: 3,
                opacity: 0.9,
                fillColor: '#175cd3',
                fillOpacity: 0.15,
            }),
            onEachFeature: (feature, featureLayer) => {
                const rows = Object.entries(feature.properties || {})
                    .filter(([, value]) => value !== null && value !== '')
                    .map(([key, value]) => '<div class="row"><span class="key">' + escapeHtml(key) + '</span><span class="value">' + escapeHtml(typeof value === 'object' ? JSON.stringify(value) : String(value)) + '</span></div>')
                    .join('');
                const distance = feature.distance_m != null
                    ? '<div class="row"><span class="key">المسافة</span><span class="value">' + Number(feature.distance_m).toFixed(2) + ' م</span></div>'
                    : '';
                featureLayer.bindPopup('<div class="map-popup"><h4>نتيجة التحليل</h4>' + distance + rows + '</div>', { maxWidth: 380 });
            },
        }).addTo(state.analysisLayer);

        if (fit && layer.getLayers().length) {
            const bounds = layer.getBounds();
            if (bounds.isValid()) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 17 });
        }

        return layer;
    };

    const loadGisFields = async () => {
        const datasetId = gisToolsDataset?.value;
        if (!datasetId || !gisToolsField) return;
        gisToolsField.innerHTML = '<option value="">جاري تحميل الحقول...</option>';
        gisToolsField.disabled = true;
        try {
            const response = await fetch('/datasets/' + datasetId + '/fields/data');
            if (!response.ok) throw new Error('تعذر تحميل حقول الطبقة.');
            const data = await response.json();
            const fields = data.data || [];
            gisToolsField.innerHTML = '<option value="">اختر الحقل</option>' +
                fields.map(field => '<option value="' + escapeHtml(field.name) + '">' + escapeHtml(field.display_name || field.name) + '</option>').join('');
            gisToolsField.disabled = fields.length === 0;
            if (!fields.length) setGisToolsStatus('هذه الطبقة لا تحتوي حقولاً ديناميكية.');
        } catch (error) {
            gisToolsField.innerHTML = '<option value="">تعذر تحميل الحقول</option>';
            setGisToolsStatus(error.message || 'تعذر تحميل الحقول.');
        }
    };

    const getSpatialPoint = () => {
        if (Number.isFinite(state.spatialPick.lat) && Number.isFinite(state.spatialPick.lng)) {
            return { lat: state.spatialPick.lat, lng: state.spatialPick.lng };
        }
        const center = map.getCenter();
        return { lat: center.lat, lng: center.lng };
    };

    const setSpatialPickMode = (enabled) => {
        state.spatialPick.active = enabled;
        if (gisRadiusPick) gisRadiusPick.textContent = enabled ? 'اضغط على الخريطة' : 'اختر نقطة';
        if (enabled) {
            setGisToolsStatus('اضغط على الخريطة لتحديد نقطة البحث.');
        } else {
            setGisToolsStatus('تم تحديد نقطة البحث.');
        }
    };

    const runAttributeQuery = async () => {
        const datasetId = gisToolsDataset?.value;
        const field = gisToolsField?.value;
        const value = gisToolsValue?.value?.trim();
        if (!datasetId || !field || !value) {
            setGisToolsStatus('اختر الطبقة والحقل وأدخل قيمة البحث.');
            return;
        }

        setGisToolsStatus('جاري البحث في خصائص الطبقة...');
        try {
            const params = new URLSearchParams({
                field,
                operator: gisToolsOperator?.value || 'contains',
                value,
            });
            const response = await fetch('/datasets/' + datasetId + '/features/query?' + params.toString());
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'تعذر تنفيذ البحث.');
            renderAnalysisResults(data);
            setGisToolsStatus('عدد النتائج: ' + (data.meta?.total ?? data.features?.length ?? 0));
        } catch (error) {
            setGisToolsStatus(error.message || 'تعذر تنفيذ البحث.');
        }
    };

    const runBboxSearch = async () => {
        const datasetId = gisToolsDataset?.value;
        if (!datasetId) {
            setGisToolsStatus('اختر الطبقة أولاً.');
            return;
        }

        const bounds = map.getBounds();
        const params = new URLSearchParams({
            bbox: [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()].join(','),
            per_page: '500',
        });
        setGisToolsStatus('جاري البحث داخل نطاق الخريطة...');
        try {
            const response = await fetch('/datasets/' + datasetId + '/features?' + params.toString());
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'تعذر تنفيذ البحث المكاني.');
            renderAnalysisResults(data, false);
            setGisToolsStatus('عدد النتائج داخل الشاشة: ' + (data.meta?.total ?? data.features?.length ?? 0));
        } catch (error) {
            setGisToolsStatus(error.message || 'تعذر تنفيذ البحث المكاني.');
        }
    };

    const runNearestSearch = async (withRadius = false) => {
        const datasetId = gisToolsDataset?.value;
        if (!datasetId) {
            setGisToolsStatus('اختر الطبقة أولاً.');
            return;
        }

        const point = getSpatialPoint();
        const params = new URLSearchParams({
            lat: point.lat,
            lng: point.lng,
            limit: '1',
        });
        if (withRadius) {
            const radius = Number(gisRadius?.value);
            if (!Number.isFinite(radius) || radius <= 0) {
                setGisToolsStatus('أدخل نصف قطر صحيح بالمتر.');
                return;
            }
            params.set('radius', String(radius));
        }

        setGisToolsStatus('جاري البحث عن أقرب معلم...');
        try {
            const response = await fetch('/datasets/' + datasetId + '/features/nearest?' + params.toString());
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'تعذر تنفيذ البحث عن أقرب معلم.');
            renderAnalysisResults(data);
            if (!data.features?.length) {
                setGisToolsStatus(withRadius ? 'لا يوجد معلم داخل نصف القطر المحدد.' : 'لا توجد معالم في الطبقة.');
                return;
            }
            setGisToolsStatus('أقرب معلم: ' + Number(data.features[0].distance_m || 0).toFixed(2) + ' متر.');
        } catch (error) {
            setGisToolsStatus(error.message || 'تعذر تنفيذ البحث عن أقرب معلم.');
        }
    };

    const startMeasurement = (type) => {
        if (state.measurement.active) return;
        state.measurement.active = true;
        state.measurement.type = type;
        setGisToolsStatus(type === 'distance'
            ? 'ارسم خط القياس على الخريطة.'
            : 'ارسم مضلع القياس على الخريطة، ثم أغلقه.');
        const options = { shapeOptions: { color: '#175cd3', weight: 3, fillOpacity: 0.15 } };
        const handler = type === 'distance'
            ? new L.Draw.Polyline(map, options)
            : new L.Draw.Polygon(map, options);
        handler.enable();
    };

    const handleMeasurementCreated = async (event) => {
        const type = state.measurement.type;
        state.measurement.active = false;
        state.measurement.type = null;

        const geometryType = event.layerType === 'polyline' ? 'LineString' : 'Polygon';
        const geometry = {
            type: geometryType,
            coordinates: geometryCoordinatesFromLayer(event.layer, geometryType),
        };

        const temp = L.geoJSON([geometry], {
            style: { color: '#175cd3', weight: 3, fillOpacity: 0.12 },
        }).addTo(state.analysisLayer);

        setGisToolsStatus('جاري حساب القياس...');
        try {
            const datasetId = gisToolsDataset?.value;
            if (!datasetId) throw new Error('اختر الطبقة أولاً لاستخدام أداة القياس.');
            const response = await fetch('/datasets/' + datasetId + '/features/measure', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ geometry }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'تعذر حساب القياس.');

            if (type === 'distance') {
                setGisToolsStatus('المسافة: ' + Number(data.meters || 0).toFixed(2) + ' م — ' + Number(data.kilometers || 0).toFixed(3) + ' كم');
            } else {
                setGisToolsStatus('المساحة: ' + Number(data.square_meters || 0).toFixed(2) + ' م² — ' + Number(data.dunums || 0).toFixed(4) + ' دونم — ' + Number(data.square_kilometers || 0).toFixed(6) + ' كم²');
            }
        } catch (error) {
            state.analysisLayer.removeLayer(temp);
            setGisToolsStatus(error.message || 'تعذر حساب القياس.');
        }
    };

    map.on('click', event => {
        if (!state.spatialPick.active) return;
        state.spatialPick.lat = event.latlng.lat;
        state.spatialPick.lng = event.latlng.lng;
        if (state.spatialPick.marker) map.removeLayer(state.spatialPick.marker);
        state.spatialPick.marker = L.circleMarker(event.latlng, {
            radius: 7,
            color: '#175cd3',
            weight: 2,
            fillColor: '#175cd3',
            fillOpacity: 0.35,
        }).addTo(map);
        setSpatialPickMode(false);
        setGisToolsStatus('نقطة البحث: ' + event.latlng.lat.toFixed(6) + '، ' + event.latlng.lng.toFixed(6));
    });

    gisToolsDataset?.addEventListener('change', loadGisFields);
    gisQuerySubmit?.addEventListener('click', runAttributeQuery);
    gisToolsValue?.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); runAttributeQuery(); } });
    gisBboxSearch?.addEventListener('click', runBboxSearch);
    gisNearestSearch?.addEventListener('click', () => runNearestSearch(false));
    gisRadiusSearch?.addEventListener('click', () => runNearestSearch(true));
    gisRadiusPick?.addEventListener('click', () => setSpatialPickMode(!state.spatialPick.active));
    gisMeasureDistance?.addEventListener('click', () => startMeasurement('distance'));
    gisMeasureArea?.addEventListener('click', () => startMeasurement('area'));
    document.getElementById('gis-tools-clear')?.addEventListener('click', () => {
        clearAnalysis();
        if (state.spatialPick.marker) {
            map.removeLayer(state.spatialPick.marker);
            state.spatialPick.marker = null;
        }
        state.spatialPick.lat = null;
        state.spatialPick.lng = null;
        setSpatialPickMode(false);
    });

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
        const color = /^#[0-9A-Fa-f]{6}$/.test(checkbox.dataset.color || '') ? checkbox.dataset.color : '#475467';
        const opacity = Math.min(1, Math.max(0, Number(checkbox.dataset.opacity || 1)));

        fetch(`/datasets/${datasetId}/features`)
            .then(response => { if (!response.ok) throw new Error(strings.loadFailed); return response.json(); })
            .then(data => {
                const layer = L.geoJSON(data.features || [], {
                    pointToLayer: (_, latlng) => L.circleMarker(latlng, {
                        radius: 6,
                        color,
                        weight: 1.5,
                        fillColor: color,
                        fillOpacity: opacity
                    }),
                    style: () => ({
                        color,
                        weight: 2,
                        opacity,
                        fillColor: color,
                        fillOpacity: opacity * 0.25
                    }),
                    onEachFeature: (feature, featureLayer) => {
                        const rows = Object.entries(feature.properties || {})
                            .filter(([, value]) => value !== null && value !== '')
                            .map(([key, value]) => `<div class="row"><span class="key">${escapeHtml(key)}</span><span class="value">${escapeHtml(typeof value === 'object' ? JSON.stringify(value) : String(value))}</span></div>`)
                            .join('');

                        const canEdit = mapElement.dataset.canEditGis === '1'
                            && checkbox.dataset.managementMode === 'web_editable'
                            && feature.id;
                        const canDelete = mapElement.dataset.canDeleteGis === '1'
                            && checkbox.dataset.managementMode === 'web_editable'
                            && feature.id;

                        const editAction = canEdit
                            ? '<button type="button" data-gis-edit-feature class="mt-3 w-full rounded-md bg-brand-600 px-3 py-2 text-xs font-medium text-white">تعديل المعلم</button>'
                            : '';
                        const deleteAction = canDelete
                            ? '<button type="button" data-gis-delete-feature class="mt-2 w-full rounded-md border border-danger-300 bg-white px-3 py-2 text-xs font-medium text-danger">حذف المعلم</button>'
                            : '';
                        const bufferAction = feature.id
                            ? '<button type="button" data-gis-buffer-feature class="mt-2 w-full rounded-md border border-brand-600 bg-white px-3 py-2 text-xs font-medium text-brand-700">إنشاء Buffer</button>'
                            : '';
                        const recordAction = canEdit && feature.dataset_record_id
                            ? '<a href="/datasets/' + datasetId + '/records/' + feature.dataset_record_id + '/edit" class="mt-2 block w-full rounded-md border border-border-strong bg-white px-3 py-2 text-center text-xs font-medium text-ink">فتح السجل المرتبط</a>'
                            : '';
                        const identifyMeta = '<div class="row"><span class="key">Feature ID</span><span class="value">' + escapeHtml(String(feature.id ?? '—')) + '</span></div>'
                            + '<div class="row"><span class="key">Record ID</span><span class="value">' + escapeHtml(String(feature.dataset_record_id ?? '—')) + '</span></div>'
                            + '<div class="row"><span class="key">نوع الهندسة</span><span class="value">' + escapeHtml(feature.geometry_type || feature.geometry?.type || '—') + '</span></div>'
                            + '<div class="row"><span class="key">SRID</span><span class="value">' + escapeHtml(String(feature.srid ?? '—')) + '</span></div>';

                        featureLayer.bindPopup(`<div class="map-popup"><h4>تفاصيل المعلم</h4>${identifyMeta}${rows}${recordAction}${bufferAction}${editAction}${deleteAction}</div>`, { maxWidth: 380 });

                        if (canEdit || canDelete || feature.id) {
                            featureLayer.on('popupopen', event => {
                                const popupElement = event.popup.getElement();
                                popupElement?.querySelector('[data-gis-edit-feature]')?.addEventListener('click', () => {
                                    map.closePopup();
                                    openFeatureEdit(datasetId, feature, featureLayer);
                                });
                                popupElement?.querySelector('[data-gis-delete-feature]')?.addEventListener('click', async () => {
                                    if (!window.confirm('هل أنت متأكد من حذف هذا المعلم؟ لا يمكن التراجع عن هذا الإجراء.')) return;

                                    const button = popupElement.querySelector('[data-gis-delete-feature]');
                                    if (button) button.disabled = true;

                                    try {
                                        const response = await fetch('/datasets/' + datasetId + '/features/' + feature.id, {
                                            method: 'DELETE',
                                            headers: {
                                                'Accept': 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                            },
                                        });

                                        if (!response.ok) {
                                            const data = await response.json().catch(() => ({}));
                                            throw new Error(data.message || 'تعذر حذف المعلم.');
                                        }

                                        if (state.editingFeature?.featureId === feature.id) {
                                            cleanupFeatureEdit();
                                        }
                                        layer.removeLayer(featureLayer);
                                        map.closePopup();
                                    } catch (error) {
                                        if (button) button.disabled = false;
                                        window.alert(error.message || 'تعذر حذف المعلم.');
                                    }
                                });
                            });
                        }
                    }
                }).addTo(map);

                state.datasetLayers[datasetId] = layer;
                checkbox.disabled = false;

                if (layer.getLayers().length) {
                    map.fitBounds(layer.getBounds(), { padding: [35, 35], maxZoom: 16 });
                }
            })
            .catch(() => {
                checkbox.checked = false;
                checkbox.disabled = false;
            });
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
    document.querySelectorAll('.dataset-toggle').forEach(checkbox => {
        checkbox.addEventListener('change', (event) => event.target.checked
            ? loadDataset(event.target.dataset.datasetId, event.target)
            : removeDataset(event.target.dataset.datasetId));

        if (checkbox.checked) {
            loadDataset(checkbox.dataset.datasetId, checkbox);
        }
    });
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
