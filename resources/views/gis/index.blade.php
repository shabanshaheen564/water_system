@extends('layouts.app')

@section('title', 'الخريطة التشغيلية')

@section('content')
<style>
    #map { min-height: 720px; height: calc(100vh - 230px); }
    .map-shell { min-height: 720px; }
    .map-marker { display:flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:9999px; border:2px solid #fff; box-shadow:0 2px 8px rgba(0,0,0,.28); font-size:14px; font-weight:700; color:#fff; }
    .map-marker.complaint { background:#b42318; }
    .map-marker.task { background:#175cd3; }
    .map-popup { min-width:250px; direction:rtl; text-align:right; }
    .map-popup h4 { margin:0 0 8px; font-weight:700; font-size:14px; }
    .map-popup .row { display:flex; justify-content:space-between; gap:16px; padding:5px 0; border-bottom:1px solid #eee; font-size:12px; }
    .map-popup .key { color:#667085; }
    .map-popup .value { color:#101828; font-weight:600; }
    .map-legend { backdrop-filter:blur(8px); background:rgba(255,255,255,.94); }
    .map-filter { max-height:0; overflow:hidden; opacity:0; transition:max-height .2s ease,opacity .2s ease; }
    .map-filter.open { max-height:420px; opacity:1; }
</style>

<div class="mx-auto max-w-[1800px] p-4 sm:p-6 lg:p-8">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold leading-[1.5] text-ink">الخريطة التشغيلية</h2>
            <p class="mt-1 text-sm text-ink-secondary">خريطة موحدة للشكاوى والمهام والبيانات الجغرافية المصرح لك بمشاهدتها.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('complaints.view')<span class="rounded-full border border-danger bg-danger-surface px-3 py-1 text-xs font-medium text-danger">الشكاوى</span>@endcan
            @can('tasks.view')<span class="rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700">المهام</span>@endcan
            @can('datasets.view')<span class="rounded-full border border-success bg-success-surface px-3 py-1 text-xs font-medium text-success">البيانات الجغرافية</span>@endcan
        </div>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card-institutional p-4"><p class="text-xs text-ink-secondary">الشكاوى الظاهرة</p><p id="stat-complaints" class="mt-1 text-2xl font-semibold text-ink">—</p></div>
        <div class="card-institutional p-4"><p class="text-xs text-ink-secondary">المهام الظاهرة</p><p id="stat-tasks" class="mt-1 text-2xl font-semibold text-ink">—</p></div>
        <div class="card-institutional p-4"><p class="text-xs text-ink-secondary">الأولوية العالية</p><p id="stat-high" class="mt-1 text-2xl font-semibold text-danger">—</p></div>
        <div class="card-institutional p-4"><p class="text-xs text-ink-secondary">البيانات الجغرافية</p><p id="stat-datasets" class="mt-1 text-2xl font-semibold text-success">—</p></div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[310px_minmax(0,1fr)]">
        <aside data-enter class="card-institutional p-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-ink">الطبقات والمعلومات</h3>
                <button id="toggle-map-filter" type="button" class="rounded-md border border-border-strong bg-white px-3 py-1.5 text-xs font-medium text-ink">الفلاتر</button>
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

            <div class="mt-4 space-y-2">
                @can('complaints.view')
                    <label class="flex cursor-pointer items-center justify-between rounded-md border border-border bg-white p-3">
                        <span class="flex items-center gap-2 text-sm font-medium text-ink"><span class="map-marker complaint !h-5 !w-5 !text-[10px]">!</span>الشكاوى</span>
                        <input id="toggle-complaints" type="checkbox" checked class="h-4 w-4 rounded border-border-strong text-brand-600">
                    </label>
                @endcan
                @can('tasks.view')
                    <label class="flex cursor-pointer items-center justify-between rounded-md border border-border bg-white p-3">
                        <span class="flex items-center gap-2 text-sm font-medium text-ink"><span class="map-marker task !h-5 !w-5 !text-[10px]">✓</span>المهام</span>
                        <input id="toggle-tasks" type="checkbox" checked class="h-4 w-4 rounded border-border-strong text-brand-600">
                    </label>
                @endcan
            </div>

            @can('datasets.view')
                <div class="mt-4 border-t border-border pt-4">
                    <h3 class="mb-3 text-xs font-semibold text-ink-secondary">البيانات الجغرافية</h3>
                    <div id="dataset-layers" class="space-y-2">
                        @forelse($spatialDatasets as $dataset)
                            <label class="flex cursor-pointer items-center justify-between gap-2 rounded-md border border-border bg-white p-3">
                                <span class="min-w-0 truncate text-sm font-medium text-ink">{{ $dataset->display_name }}</span>
                                <input type="checkbox" class="dataset-toggle h-4 w-4 rounded border-border-strong text-brand-600" data-dataset-id="{{ $dataset->id }}">
                            </label>
                        @empty
                            <p class="text-xs text-ink-muted">لا توجد بيانات جغرافية مكانية مفعلة.</p>
                        @endforelse
                    </div>
                </div>
            @endcan

            <div class="mt-4 border-t border-border pt-4 space-y-2">
                <button id="zoom-to-visible" type="button" class="w-full rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white">إظهار جميع العناصر</button>
                <button id="reset-map" type="button" class="w-full rounded-md border border-border-strong bg-white px-3 py-2 text-sm font-medium text-ink">إعادة ضبط الخريطة</button>
            </div>
        </aside>

        <section data-enter class="card-institutional map-shell relative overflow-hidden">
            <div id="map" data-operational-map="true" data-map-data-url="{{ route('map.data') }}" data-map-url="{{ url('/map') }}" data-msg-load-failed="تعذر تحميل بيانات الخريطة.">
                <span class="sr-only">صورة جوية / ستالايت</span>
            </div>
            <div class="map-legend absolute bottom-4 right-4 z-[500] rounded-lg border border-border p-3 shadow-sm">
                <p class="mb-2 text-xs font-semibold text-ink">مفتاح الخريطة</p>
                <div class="space-y-2 text-xs text-ink-secondary">
                    <div class="flex items-center gap-2"><span class="map-marker complaint !h-5 !w-5 !text-[10px]">!</span>شكوى</div>
                    <div class="flex items-center gap-2"><span class="map-marker task !h-5 !w-5 !text-[10px]">✓</span>مهمة</div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
