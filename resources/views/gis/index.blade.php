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
</style>

<div class="map-shell">
    <div id="map" data-operational-map="true" data-can-edit-gis="{{ auth()->user()->can('datasets.update') ? '1' : '0' }}" data-map-data-url="{{ route('map.data') }}" data-map-url="{{ url('/map') }}" data-msg-load-failed="تعذر تحميل بيانات الخريطة." data-satellite-layer-label="صورة جوية / ستالايت">
        <span class="sr-only">الخريطة التفاعلية</span>
    </div>

    <div class="map-panel absolute end-0 top-0 bottom-0 z-[1000] w-[330px] max-w-[calc(100vw-32px)] overflow-y-auto rounded-s-xl border border-border border-e-0 p-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-ink">الخريطة التشغيلية</h2>
                <p class="mt-0.5 text-xs text-ink-secondary">الشكاوى والمهام والطبقات الجغرافية</p>
            </div>
            <button id="toggle-map-filter" type="button" class="rounded-md border border-border-strong bg-white px-3 py-1.5 text-xs font-medium text-ink">الفلاتر</button>
        </div>

        <div class="mt-3 rounded-md border border-border bg-surface-1 p-3">
            <label for="map-place-search" class="mb-2 block text-xs font-semibold text-ink">البحث عن مكان على الخريطة</label>
            <div class="flex gap-2">
                <input id="map-place-search" type="search" placeholder="ابحث عن مسجد، شارع، حي، معلم..." class="min-w-0 flex-1 rounded-md border border-border-strong bg-white px-3 py-2 text-sm">
                <button id="map-place-search-button" type="button" class="rounded-md bg-brand-600 px-3 py-2 text-xs font-medium text-white">بحث</button>
            </div>
            <p id="map-place-search-status" class="mt-2 text-xs text-ink-secondary"></p>
            <div id="map-place-search-results" class="mt-2 max-h-56 overflow-y-auto rounded-md border border-border bg-white"></div>
        </div>

        <div id="map-filter" class="map-filter mt-3 border-y border-border py-3">
            <label class="mb-2 block text-xs font-medium text-ink-secondary">بحث</label>
            <input id="map-search" type="search" placeholder="رقم الشكوى، المهمة، العنوان..." class="w-full rounded-md border border-border-strong bg-white px-3 py-2 text-sm outline-none focus:border-brand-600">
            <div class="mt-3 grid grid-cols-2 gap-2">
                <select id="map-status" class="rounded-md border border-border-strong bg-white px-2 py-2 text-xs"><option value="">كل الحالات</option></select>
                <select id="map-priority" class="rounded-md border border-border-strong bg-white px-2 py-2 text-xs"><option value="">كل الأولويات</option><option value="urgent">عاجلة</option><option value="high">عالية</option><option value="medium">متوسطة</option><option value="low">منخفضة</option></select>
            </div>
            <button id="clear-map-filter" type="button" class="mt-3 w-full rounded-md border border-border-strong bg-white px-3 py-2 text-xs font-medium text-ink">مسح الفلاتر</button>
        </div>

        <div class="mt-3 grid grid-cols-2 gap-2">
            @can('complaints.view')
                <label class="flex cursor-pointer items-center justify-between rounded-md border border-border bg-white p-2.5">
                    <span class="flex items-center gap-2 text-xs font-medium text-ink"><span class="map-marker complaint !h-5 !w-5 !text-[10px]">!</span>الشكاوى</span>
                    <input id="toggle-complaints" type="checkbox" checked class="h-4 w-4 rounded border-border-strong text-brand-600">
                </label>
            @endcan
            @can('tasks.view')
                <label class="flex cursor-pointer items-center justify-between rounded-md border border-border bg-white p-2.5">
                    <span class="flex items-center gap-2 text-xs font-medium text-ink"><span class="map-marker task !h-5 !w-5 !text-[10px]">✓</span>المهام</span>
                    <input id="toggle-tasks" type="checkbox" checked class="h-4 w-4 rounded border-border-strong text-brand-600">
                </label>
            @endcan
        </div>

        @can('datasets.view')
            @can('datasets.create')
                <div class="mt-3 border-t border-border pt-3">
                    <h3 class="mb-2 text-xs font-semibold text-ink-secondary">تحرير المعالم</h3>
                    <div class="space-y-2">
                        <select id="gis-edit-dataset" class="w-full rounded-md border border-border-strong bg-white px-2 py-2 text-xs">
                            <option value="">اختر طبقة للإضافة</option>
                            @foreach($spatialDatasets->where('management_mode', 'web_editable') as $dataset)
                                <option value="{{ $dataset->id }}" data-geometry-type="{{ $dataset->geometry_type }}">{{ $dataset->display_name }} — {{ $dataset->geometry_type }}</option>
                            @endforeach
                        </select>
                        <button id="gis-start-drawing" type="button" disabled class="w-full rounded-md bg-brand-600 px-3 py-2 text-xs font-medium text-white disabled:cursor-not-allowed disabled:opacity-50">بدء رسم معلم</button>
                        <p id="gis-drawing-status" class="text-[11px] leading-5 text-ink-secondary">اختر طبقة مكانية ثم ابدأ الرسم.</p>
                    </div>
                </div>
            @endcan

            <div class="mt-3 border-t border-border pt-3">
                <h3 class="mb-2 text-xs font-semibold text-ink-secondary">الطبقات الجغرافية</h3>
                <div id="dataset-layers" class="max-h-40 space-y-2 overflow-y-auto">
                    @forelse($spatialDatasets as $dataset)
                        <label class="flex cursor-pointer items-center justify-between gap-2 rounded-md border border-border bg-white p-2.5">
                            <span class="min-w-0 truncate text-xs font-medium text-ink">{{ $dataset->display_name }}</span>
                            <input type="checkbox" class="dataset-toggle h-4 w-4 rounded border-border-strong text-brand-600"
       data-dataset-id="{{ $dataset->id }}"
       data-geometry-type="{{ $dataset->geometry_type }}"
       data-management-mode="{{ $dataset->management_mode }}"
       data-opacity="{{ $dataset->map_opacity }}"
       data-color="{{ $dataset->display_color }}"
       {{ $dataset->default_visible ? 'checked' : '' }}>
                        </label>
                    @empty
                        <p class="text-xs text-ink-muted">لا توجد طبقات مكانية مفعلة.</p>
                    @endforelse
                </div>
            </div>
        @endcan

        <div class="mt-3 grid grid-cols-2 gap-2 border-t border-border pt-3">
            <button id="zoom-to-visible" type="button" class="rounded-md bg-brand-600 px-3 py-2 text-xs font-medium text-white">إظهار العناصر</button>
            <button id="reset-map" type="button" class="rounded-md border border-border-strong bg-white px-3 py-2 text-xs font-medium text-ink">إعادة الضبط</button>
        </div>

        <div class="mt-3 grid grid-cols-4 gap-2 border-t border-border pt-3 text-center">
            <div><p class="text-[10px] text-ink-secondary">شكاوى</p><p id="stat-complaints" class="mt-0.5 text-sm font-semibold text-ink">—</p></div>
            <div><p class="text-[10px] text-ink-secondary">مهام</p><p id="stat-tasks" class="mt-0.5 text-sm font-semibold text-ink">—</p></div>
            <div><p class="text-[10px] text-ink-secondary">عالية</p><p id="stat-high" class="mt-0.5 text-sm font-semibold text-danger">—</p></div>
            <div><p class="text-[10px] text-ink-secondary">طبقات</p><p id="stat-datasets" class="mt-0.5 text-sm font-semibold text-success">—</p></div>
        </div>
    </div>

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

    <div class="absolute bottom-4 end-4 z-[1000] rounded-lg border border-border bg-white/95 p-3 shadow-sm backdrop-blur">
        <p class="mb-2 text-xs font-semibold text-ink">مفتاح الخريطة</p>
        <div class="flex gap-4 text-xs text-ink-secondary">
            <div class="flex items-center gap-1.5"><span class="map-marker complaint !h-5 !w-5 !text-[10px]">!</span>شكوى</div>
            <div class="flex items-center gap-1.5"><span class="map-marker task !h-5 !w-5 !text-[10px]">✓</span>مهمة</div>
        </div>
    </div>
</div>
@endsection
