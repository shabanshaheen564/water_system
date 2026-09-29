@extends('layouts.app')

@section('title', 'الخريطة')

@section('content')
<style>
    #map { width:100%; height:calc(100vh - 64px); min-height:720px; }
    .map-shell { position:relative; width:100%; height:calc(100vh - 64px); min-height:720px; overflow:hidden; background:#eef2f6; }
    .map-marker { display:flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:9999px; border:2px solid #fff; box-shadow:0 2px 8px rgba(0,0,0,.28); font-size:14px; font-weight:700; color:#fff; }
    .map-marker.complaint { background:#b42318; }
    .map-marker.task { background:#175cd3; }
    .map-popup { min-width:250px; direction:rtl; text-align:right; }
    .map-popup h4 { margin:0 0 8px; font-weight:700; font-size:14px; }
    .map-popup .row { display:flex; justify-content:space-between; gap:16px; padding:5px 0; border-bottom:1px solid #eee; font-size:12px; }
    .map-popup .key { color:#667085; }
    .map-popup .value { color:#101828; font-weight:600; }
    .map-panel { backdrop-filter:blur(10px); background:rgba(255,255,255,.96); box-shadow:0 8px 30px rgba(16,24,40,.14); }
    .map-filter { max-height:0; overflow:hidden; opacity:0; transition:max-height .2s ease,opacity .2s ease; }
    .map-filter.open { max-height:420px; opacity:1; }
    .leaflet-control-layers { direction:rtl; text-align:right; }
    @media (max-width:1023px) {
        #map,.map-shell { height:calc(100vh - 64px); min-height:600px; }
        .map-panel { max-width:calc(100vw - 32px); }
    }

    .map-panel { border-radius: 0 14px 14px 0; }
    /* Keep Leaflet's native controls above the map content.
       The right panel is 330px wide, so only the zoom control is shifted
       left; the attribution remains centered independently. */
    .map-shell .leaflet-control-container .leaflet-bottom.leaflet-left {
        left: 0 !important;
        right: auto !important;
        bottom: 16px !important;
        width: 100% !important;
        z-index: 1001 !important;
        pointer-events: none !important;
    }
    .map-shell .leaflet-bottom.leaflet-left .leaflet-control-zoom {
        position: absolute !important;
        right: 346px !important;
        bottom: 72px !important;
        margin: 0 !important;
        transform: none !important;
        z-index: 1001 !important;
        direction: ltr !important;
        pointer-events: auto !important;
    }
    .map-shell .leaflet-control-zoom a {
        direction: ltr !important;
        text-align: center !important;
    }
    .map-shell .leaflet-control-attribution {
        position: fixed !important;
        left: 50% !important;
        right: auto !important;
        bottom: 0 !important;
        margin: 0 !important;
        transform: translateX(-50%) !important;
        white-space: nowrap;
        z-index: 1001 !important;
    }
    @media (max-width:1023px) {
        .map-shell .leaflet-bottom.leaflet-left .leaflet-control-zoom {
            right: 16px !important;
            bottom: 72px !important;
        }
    }

    .map-tool-dock{position:absolute;inset-block:16px;inset-inline-end:16px;z-index:1000;width:350px;max-width:calc(100vw - 32px);overflow-y:auto;padding:12px;border:1px solid rgba(255,255,255,.72);border-radius:18px;background:rgba(255,255,255,.90);backdrop-filter:blur(18px);box-shadow:0 18px 55px rgba(16,24,40,.18);direction:rtl}
    .map-tool-brand{display:flex;align-items:center;gap:10px;padding:6px 6px 12px}.map-tool-brand-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:12px;background:#8b1a1a;color:#fff;font-size:22px;box-shadow:0 7px 18px rgba(139,26,26,.24)}.map-tool-brand strong{display:block;font-size:14px;color:#101828}.map-tool-brand span{display:block;font-size:10px;color:#667085;direction:ltr;text-align:right;letter-spacing:.04em}
    .map-tool-trigger{width:100%;display:grid;grid-template-columns:34px 1fr 18px;align-items:center;gap:9px;min-height:48px;padding:7px 8px;border:1px solid #e2e5e9;border-radius:12px;background:#fff;color:#344054;text-align:right;margin-top:7px;cursor:pointer;transition:.18s ease}.map-tool-trigger:hover{border-color:#cbd1d8;box-shadow:0 5px 16px rgba(16,24,40,.07);transform:translateY(-1px)}.map-tool-trigger.active{border-color:#d6a6a6;background:#fffafa}.map-tool-icon{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#f4f5f7;color:#8b1a1a;font-size:20px;font-weight:700}.map-tool-label{font-size:12px;font-weight:700}.map-tool-chevron{font-size:22px;color:#98a2b3;transition:transform .18s ease;transform:rotate(180deg)}.map-tool-trigger:not(.active) .map-tool-chevron{transform:rotate(0deg)}
    .map-tool-section{display:none;margin-top:7px;border:1px solid #e2e5e9;border-radius:12px;background:#f8fafc;overflow:hidden;animation:map-tool-in .18s ease}.map-tool-section.open{display:block}.map-tool-section-head{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:10px 11px;background:#fff;border-bottom:1px solid #e2e5e9}.map-tool-section-head strong{display:block;font-size:12px;color:#101828}.map-tool-section-head span{display:block;margin-top:2px;font-size:10px;color:#667085}.map-tool-body{padding:10px}.map-tool-input{min-height:36px;border:1px solid #cbd1d8;border-radius:9px;background:#fff;padding:7px 9px;font-size:11px;color:#344054;outline:none}.map-tool-input:focus{border-color:#8b1a1a;box-shadow:0 0 0 3px rgba(139,26,26,.08)}
    .map-tool-primary,.map-tool-secondary,.map-tool-mini{border-radius:9px;min-height:36px;padding:7px 10px;font-size:11px;font-weight:700;cursor:pointer;transition:.15s ease}.map-tool-primary{border:1px solid #8b1a1a;background:#8b1a1a;color:#fff}.map-tool-secondary{border:1px solid #cbd1d8;background:#fff;color:#344054}.map-tool-mini{min-height:28px;padding:4px 8px;border:1px solid #d0d5dd;background:#fff;color:#475467}.map-tool-primary:hover{background:#741515}.map-tool-secondary:hover,.map-tool-mini:hover{border-color:#98a2b3;background:#f9fafb}.map-tool-status{margin:6px 0 0;font-size:10px;line-height:1.7;color:#667085}.map-search-results{max-height:220px;overflow-y:auto;margin-top:7px;border:1px solid #e2e5e9;border-radius:9px;background:#fff}
    .map-layer-switch,.map-dataset-row{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 9px;border:1px solid #e2e5e9;border-radius:9px;background:#fff;font-size:11px;color:#344054}.map-dataset-row+.map-dataset-row{margin-top:6px}.map-symbol{display:inline-grid;place-items:center;width:22px;height:22px;flex:0 0 22px;border:2px solid #fff;border-radius:50%;box-shadow:0 2px 7px rgba(16,24,40,.2);color:#fff;font-size:10px;font-weight:800}.map-symbol.complaint{background:#b42318}.map-symbol.task{background:#175cd3}
    .map-legend{position:absolute;bottom:16px;inset-inline-start:16px;z-index:1000;min-width:230px;max-width:300px;padding:11px 12px;border:1px solid rgba(255,255,255,.8);border-radius:14px;background:rgba(255,255,255,.92);backdrop-filter:blur(14px);box-shadow:0 10px 30px rgba(16,24,40,.14);direction:rtl}.map-legend-head{display:flex;align-items:end;justify-content:space-between;gap:12px;margin-bottom:9px}.map-legend-title{font-size:12px;font-weight:800;color:#101828}.map-legend-subtitle{font-size:9px;color:#98a2b3}.map-legend-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}.map-legend-item{display:flex;align-items:center;gap:7px;min-width:0;font-size:10px;color:#475467}.map-legend-dot.point{width:12px;height:12px;border-radius:50%;background:#667085;border:2px solid #fff;box-shadow:0 0 0 1px #98a2b3}.map-legend-line{width:20px;height:4px;border-radius:99px;background:#667085}.map-legend-area{width:16px;height:12px;border:2px solid #667085;border-radius:3px;background:rgba(102,112,133,.16)}.map-tool-actions{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-top:8px}.map-tool-actions button{height:38px;border:1px solid #d0d5dd;border-radius:10px;background:#fff;color:#344054;font-size:16px;cursor:pointer}.map-tool-actions button:hover{border-color:#98a2b3;background:#f9fafb}.map-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:5px;margin-top:8px;padding-top:9px;border-top:1px solid #e2e5e9;text-align:center}.map-stats span{display:block;font-size:8px;color:#98a2b3}.map-stats strong{display:block;margin-top:1px;font-size:12px;color:#344054}@keyframes map-tool-in{from{opacity:0;transform:translateY(-3px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:1023px){.map-tool-dock{inset-inline:10px;inset-block:10px;width:auto;max-width:none;max-height:calc(100vh - 84px)}.map-legend{bottom:10px;inset-inline-start:10px;max-width:calc(100vw - 20px)}}
</style>

<div class="map-shell">
    <div id="map" data-operational-map="true" data-can-edit-gis="{{ auth()->user()->can('datasets.update') ? '1' : '0' }}"
         data-can-delete-gis="{{ auth()->user()->can('datasets.delete') ? '1' : '0' }}" data-map-data-url="{{ route('map.data') }}" data-map-url="{{ url('/map') }}" data-msg-load-failed="تعذر تحميل بيانات الخريطة." data-satellite-layer-label="صورة جوية / ستالايت">
        <span class="sr-only">الخريطة التفاعلية</span>
    </div>

    <aside class="map-tool-dock" aria-label="أدوات الخريطة">
        <div class="map-tool-brand"><span class="map-tool-brand-icon">⌖</span><div><strong>الخريطة</strong><span>GIS Control</span></div></div>
        <button type="button" class="map-tool-trigger active" data-map-tool="search" aria-expanded="true" aria-controls="map-tool-search"><span class="map-tool-icon">⌕</span><span class="map-tool-label">البحث</span><span class="map-tool-chevron">‹</span></button>
        <section id="map-tool-search" class="map-tool-section open" data-map-tool-section><div class="map-tool-section-head"><div><strong>البحث عن مكان</strong><span>شارع، مسجد، حي، معلم</span></div></div><div class="map-tool-body"><div class="flex gap-2"><input id="map-place-search" type="search" placeholder="ابحث عن مكان..." class="map-tool-input min-w-0 flex-1"><button id="map-place-search-button" type="button" class="map-tool-primary">بحث</button></div><p id="map-place-search-status" class="map-tool-status"></p><div id="map-place-search-results" class="map-search-results"></div></div></section>
        @can('datasets.view')
        <button type="button" class="map-tool-trigger" data-map-tool="gis" aria-expanded="false" aria-controls="map-tool-gis"><span class="map-tool-icon">⌁</span><span class="map-tool-label">أدوات GIS</span><span class="map-tool-chevron">‹</span></button>
        <section id="map-tool-gis" class="map-tool-section" data-map-tool-section><div class="map-tool-section-head"><div><strong>أدوات GIS</strong><span>استعلام، Buffer، قياس</span></div><button id="gis-tools-clear" type="button" class="map-tool-mini">مسح</button></div><div class="map-tool-body space-y-2">
            <select id="gis-query-dataset" class="map-tool-input w-full"><option value="">اختر الطبقة</option>@foreach($spatialDatasets as $dataset)<option value="{{ $dataset->id }}" data-geometry-type="{{ $dataset->geometry_type }}">{{ $dataset->display_name }}</option>@endforeach</select>
            <div class="grid grid-cols-[1fr_auto] gap-2"><select id="gis-query-field" class="map-tool-input min-w-0" disabled><option value="">اختر الحقل</option></select><select id="gis-query-operator" class="map-tool-input"><option value="contains">يحتوي</option><option value="equals">يساوي</option></select></div>
            <div class="flex gap-2"><input id="gis-query-value" type="search" placeholder="قيمة البحث..." class="map-tool-input min-w-0 flex-1"><button id="gis-query-submit" type="button" class="map-tool-primary">بحث</button></div>
            <div class="grid grid-cols-2 gap-2"><button id="gis-bbox-search" type="button" class="map-tool-secondary">داخل الشاشة</button><button id="gis-nearest-search" type="button" class="map-tool-secondary">أقرب معلم</button></div>
            <div class="grid grid-cols-[1fr_auto] gap-2"><input id="gis-radius" type="number" min="1" step="1" value="500" class="map-tool-input"><button id="gis-radius-pick" type="button" class="map-tool-secondary">اختر نقطة</button></div>
            <button id="gis-radius-search" type="button" class="map-tool-secondary w-full">ضمن نصف القطر</button><div class="grid grid-cols-2 gap-2"><button id="gis-measure-distance" type="button" class="map-tool-secondary">قياس مسافة</button><button id="gis-measure-area" type="button" class="map-tool-secondary">قياس مساحة</button></div><p id="gis-tools-status" class="map-tool-status min-h-5"></p>
        </div></section>
        @endcan
        <button type="button" class="map-tool-trigger" data-map-tool="analysis" aria-expanded="false" aria-controls="map-tool-analysis"><span class="map-tool-icon">◈</span><span class="map-tool-label">التحليل</span><span class="map-tool-chevron">‹</span></button>
        <section id="map-tool-analysis" class="map-tool-section" data-map-tool-section><div class="map-tool-section-head"><div><strong>التحليل المكاني</strong><span>تقاطع، خدمة، كثافة، خطورة</span></div><button id="gis-analysis-clear" type="button" class="map-tool-mini">مسح</button></div><div class="map-tool-body space-y-2">
            <select id="gis-analysis-operation" class="map-tool-input w-full"><option value="">اختر نوع التحليل</option><option value="intersection">Spatial Intersection — تقاطع مكاني</option><option value="within">Within — داخل</option><option value="contains">Contains — يحتوي</option><option value="service_area">Service Area — نطاق خدمة</option><option value="affected_area">Affected Area — منطقة متأثرة</option><option value="density">Density / Heatmap — كثافة</option><option value="risk_zone">Risk Zone — منطقة خطورة</option></select>
            <select id="gis-analysis-dataset" class="map-tool-input w-full"><option value="">اختر الطبقة المصدر</option>@foreach($spatialDatasets as $dataset)<option value="{{ $dataset->id }}">{{ $dataset->display_name }}</option>@endforeach</select>
            <select id="gis-analysis-target" class="hidden map-tool-input w-full"><option value="">اختر الطبقة المستهدفة</option>@foreach($spatialDatasets as $dataset)<option value="{{ $dataset->id }}">{{ $dataset->display_name }}</option>@endforeach</select>
            <input id="gis-analysis-distance" type="number" min="1" step="1" value="500" placeholder="مسافة التحليل بالمتر" class="hidden map-tool-input w-full">
            <div id="gis-analysis-density-options" class="hidden grid grid-cols-2 gap-2"><input id="gis-analysis-cell-size" type="number" min="10" max="10000" step="10" value="250" placeholder="حجم الخلية بالمتر" class="map-tool-input"><input id="gis-analysis-min-count" type="number" min="1" step="1" value="2" placeholder="الحد الأدنى" class="map-tool-input"></div>
            <button id="gis-analysis-run" type="button" class="map-tool-primary w-full">تشغيل التحليل</button><p id="gis-analysis-status" class="map-tool-status min-h-5"></p>
        </div></section>
        <button type="button" class="map-tool-trigger" data-map-tool="layers" aria-expanded="false" aria-controls="map-tool-layers"><span class="map-tool-icon">▦</span><span class="map-tool-label">الطبقات</span><span class="map-tool-chevron">‹</span></button>
        <section id="map-tool-layers" class="map-tool-section" data-map-tool-section><div class="map-tool-section-head"><div><strong>الطبقات الجغرافية</strong><span>تشغيل وإخفاء الطبقات</span></div></div><div class="map-tool-body">
            <div class="grid grid-cols-2 gap-2">
                @can('complaints.view')<label class="map-layer-switch"><span><span class="map-symbol complaint">!</span>الشكاوى</span><input id="toggle-complaints" type="checkbox" checked></label>@endcan
                @can('tasks.view')<label class="map-layer-switch"><span><span class="map-symbol task">✓</span>المهام</span><input id="toggle-tasks" type="checkbox" checked></label>@endcan
            </div>
            @can('datasets.view')<div class="mt-3 border-t border-border pt-3"><div id="dataset-layers" class="max-h-52 space-y-2 overflow-y-auto">@forelse($spatialDatasets as $dataset)<label class="map-dataset-row"><span class="min-w-0 truncate">{{ $dataset->display_name }}</span><input type="checkbox" class="dataset-toggle" data-dataset-id="{{ $dataset->id }}" data-geometry-type="{{ $dataset->geometry_type }}" data-management-mode="{{ $dataset->management_mode }}" data-opacity="{{ $dataset->map_opacity }}" data-color="{{ $dataset->display_color }}" {{ $dataset->default_visible ? 'checked' : '' }}></label>@empty<p class="text-xs text-ink-muted">لا توجد طبقات مكانية مفعلة.</p>@endforelse</div></div>@endcan
        </div></section>
        @can('datasets.view') @can('datasets.create')
        <button type="button" class="map-tool-trigger" data-map-tool="editing" aria-expanded="false" aria-controls="map-tool-editing"><span class="map-tool-icon">✎</span><span class="map-tool-label">التحرير</span><span class="map-tool-chevron">‹</span></button>
        <section id="map-tool-editing" class="map-tool-section" data-map-tool-section><div class="map-tool-section-head"><div><strong>تحرير المعالم</strong><span>إضافة وتعديل المعالم</span></div></div><div class="map-tool-body space-y-2"><select id="gis-edit-dataset" class="map-tool-input w-full"><option value="">اختر طبقة للإضافة</option>@foreach($spatialDatasets->where('management_mode', 'web_editable') as $dataset)<option value="{{ $dataset->id }}" data-geometry-type="{{ $dataset->geometry_type }}">{{ $dataset->display_name }} — {{ $dataset->geometry_type }}</option>@endforeach</select><button id="gis-start-drawing" type="button" disabled class="map-tool-primary w-full disabled:cursor-not-allowed disabled:opacity-50">بدء رسم معلم</button><p id="gis-drawing-status" class="map-tool-status">اختر طبقة مكانية ثم ابدأ الرسم.</p></div></section>
        @endcan @endcan
        <button type="button" class="map-tool-trigger" data-map-tool="filters" aria-expanded="false" aria-controls="map-tool-filters"><span class="map-tool-icon">≡</span><span class="map-tool-label">الفلاتر</span><span class="map-tool-chevron">‹</span></button>
        <section id="map-tool-filters" class="map-tool-section" data-map-tool-section><div class="map-tool-section-head"><div><strong>تصفية التشغيل</strong><span>شكوى، مهمة، حالة، أولوية</span></div></div><div class="map-tool-body"><input id="map-search" type="search" placeholder="رقم الشكوى، المهمة، العنوان..." class="map-tool-input w-full"><div class="mt-2 grid grid-cols-2 gap-2"><select id="map-status" class="map-tool-input"><option value="">كل الحالات</option></select><select id="map-priority" class="map-tool-input"><option value="">كل الأولويات</option><option value="urgent">عاجلة</option><option value="high">عالية</option><option value="medium">متوسطة</option><option value="low">منخفضة</option></select></div><button id="clear-map-filter" type="button" class="map-tool-secondary mt-2 w-full">مسح الفلاتر</button></div></section>
        <div class="map-tool-actions"><button id="zoom-to-visible" type="button" title="إظهار العناصر" aria-label="إظهار العناصر">⛶</button><button id="reset-map" type="button" title="إعادة ضبط الخريطة" aria-label="إعادة ضبط الخريطة">⌂</button></div>
        <div class="map-stats"><div><span>شكاوى</span><strong id="stat-complaints">—</strong></div><div><span>مهام</span><strong id="stat-tasks">—</strong></div><div><span>عالية</span><strong id="stat-high">—</strong></div><div><span>طبقات</span><strong id="stat-datasets">—</strong></div></div>
    </aside>

    <div id="gis-attribute-modal" class="fixed inset-0 z-[2000] hidden items-center justify-center bg-black/40 p-4" dir="rtl">
        <div class="w-full max-w-lg rounded-xl border border-border bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-border px-5 py-4">
                <div>
                    <h3 id="gis-attribute-title" class="text-sm font-semibold text-ink">خصائص المعلم</h3>
                    <p id="gis-attribute-dataset-name" class="mt-1 text-xs text-ink-secondary"></p>
                </div>
                <button id="gis-attribute-close" type="button" class="rounded-md px-2 py-1 text-lg text-ink-secondary hover:bg-surface-1" aria-label="إغلاق">×</button>
            </div>
            <div class="px-5 pt-4">
                <div class="rounded-md border border-border bg-surface-1 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold text-ink">موقع المعلم</p>
                            <p id="gis-edit-location-status" class="mt-1 text-[11px] text-ink-secondary">يمكنك تعديل الموقع من الزر.</p>
                        </div>
                        <button id="gis-edit-location" type="button" class="shrink-0 rounded-md border border-brand-600 bg-white px-3 py-2 text-xs font-medium text-brand-700">تعديل الموقع</button>
                    </div>
                </div>
            </div>
            <div id="gis-attribute-fields" class="max-h-[55vh] space-y-3 overflow-y-auto px-5 py-4"></div>
            <p id="gis-attribute-error" class="hidden px-5 pb-3 text-xs text-danger"></p>
            <div class="flex justify-end gap-2 border-t border-border px-5 py-4">
                <button id="gis-attribute-cancel" type="button" class="rounded-md border border-border-strong bg-white px-4 py-2 text-xs font-medium text-ink">إلغاء</button>
                <button id="gis-attribute-save" type="button" class="rounded-md bg-brand-600 px-4 py-2 text-xs font-medium text-white">حفظ المعلم</button>
            </div>
        </div>
    </div>

