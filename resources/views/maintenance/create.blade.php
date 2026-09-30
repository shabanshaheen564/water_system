@extends('layouts.app')

@section('title', 'طلب صيانة جديد')

@section('content')
<div class="mx-auto max-w-6xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-ink">إنشاء طلب صيانة</h2>
        <p class="mt-1 text-sm text-ink-secondary">اختر نوع الأصل ثم المعلم الفعلي من GIS. لا يتم إدخال اسم الطبقة أو رقم الأصل يدويًا.</p>
    </div>

    @if($errors->any())
        <div class="mb-5 rounded-md border border-danger bg-danger-surface p-4 text-sm text-danger">
            <ul class="list-disc pr-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('maintenance.store') }}" class="grid gap-5 lg:grid-cols-5">
        @csrf
        <div class="card-institutional grid gap-5 p-6 lg:col-span-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">نوع الأصل / الطبقة</label>
                <select id="dataset_id" class="input-institutional w-full" required>
                    <option value="">اختر نوع الأصل</option>
                    @foreach($datasets as $dataset)
                        <option value="{{ $dataset->id }}">{{ $dataset->display_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-ink">بحث داخل الأصول</label>
                <input id="feature_search" type="search" class="input-institutional w-full" placeholder="رقم/معرّف أو قيمة من بيانات الأصل">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-ink">المعلم الفعلي</label>
                <select id="gis_feature_id" name="gis_feature_id" class="input-institutional w-full" required disabled>
                    <option value="">اختر نوع الأصل أولًا</option>
                </select>
                <p id="feature_status" class="mt-1 text-xs text-ink-muted"></p>
            </div>

            <div id="feature_data" class="rounded-md border border-border bg-surface-1 p-3 text-xs text-ink-secondary">
                اختر أصلًا لعرض بياناته المكانية والوصفية.
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">الأولوية</label>
                    <select name="priority" class="input-institutional w-full">
                        <option value="medium">متوسطة</option><option value="low">منخفضة</option>
                        <option value="high">عالية</option><option value="urgent">عاجلة</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">الفني المسند إليه</label>
                    <select name="assigned_to" class="input-institutional w-full">
                        <option value="">بدون إسناد</option>
                        @foreach($technicians as $technician)<option value="{{ $technician->id }}">{{ $technician->name }}</option>@endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-ink">وصف المشكلة <span class="text-danger">*</span></label>
                <textarea name="problem_description" rows="4" class="input-institutional w-full" required>{{ old('problem_description') }}</textarea>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">وصف العطل</label>
                <textarea name="fault_description" rows="3" class="input-institutional w-full">{{ old('fault_description') }}</textarea>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">ملاحظات</label>
                <textarea name="notes" rows="3" class="input-institutional w-full">{{ old('notes') }}</textarea>
            </div>

            <div class="flex gap-2 border-t border-border pt-4">
                <button class="rounded-md bg-brand-600 px-5 py-2 text-sm font-medium text-white">حفظ الطلب</button>
                <a href="{{ route('maintenance.index') }}" class="rounded-md border border-border-strong bg-white px-5 py-2 text-sm">إلغاء</a>
            </div>
        </div>

        <div class="card-institutional overflow-hidden lg:col-span-3">
            <div class="border-b border-border px-5 py-4">
                <h3 class="font-semibold text-ink">موقع الأصل</h3>
                <p class="mt-1 text-xs text-ink-secondary">يتم عرض المعلم الحقيقي من GIS عند اختياره.</p>
            </div>
            <div id="maintenance-create-map" class="h-[560px] w-full"></div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dataset = document.getElementById('dataset_id');
    const feature = document.getElementById('gis_feature_id');
    const search = document.getElementById('feature_search');
    const status = document.getElementById('feature_status');
    const dataBox = document.getElementById('feature_data');
    const map = L.map('maintenance-create-map', { zoomControl: true }).setView([31.417, 34.368], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    let featureLayer = null;
    let featureItems = [];

    const escapeHtml = value => {
        const div = document.createElement('div'); div.textContent = value ?? ''; return div.innerHTML;
    };

    const renderData = item => {
        if (!item) { dataBox.textContent = 'اختر أصلًا لعرض بياناته المكانية والوصفية.'; return; }
        const values = item.values || {};
        const rows = Object.entries(values).filter(([, v]) => v !== null && v !== '')
            .slice(0, 12)
            .map(([key, value]) => '<div class="flex justify-between gap-3 border-b border-border py-1 last:border-0"><span class="font-medium">' + escapeHtml(key) + '</span><span>' + escapeHtml(typeof value === 'object' ? JSON.stringify(value) : String(value)) + '</span></div>').join('');
        dataBox.innerHTML = '<div class="mb-2 font-semibold text-ink">المعرّف: ' + escapeHtml(item.identifier || ('GIS #' + item.id)) + '</div>' + (rows || '<div>لا توجد قيم وصفية إضافية.</div>');
    };

    const renderSelected = () => {
        const item = featureItems.find(item => String(item.id) === String(feature.value));
        renderData(item);
        if (featureLayer) { map.removeLayer(featureLayer); featureLayer = null; }
        if (!item?.geojson?.geometry) return;
        featureLayer = L.geoJSON(item.geojson, {
            pointToLayer: (_, latlng) => L.circleMarker(latlng, { radius: 8, weight: 2 })
        }).addTo(map);
        const bounds = featureLayer.getBounds();
        if (bounds.isValid()) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 17 });
    };

    const loadFeatures = async () => {
        if (!dataset.value) {
            feature.innerHTML = '<option value="">اختر نوع الأصل أولًا</option>'; feature.disabled = true; return;
        }
        feature.disabled = true; feature.innerHTML = '<option value="">جاري تحميل الأصول...</option>';
        status.textContent = 'جاري تحميل المعالم المكانية...';
        try {
            const params = new URLSearchParams({ per_page: '500' });
            if (search.value.trim()) params.set('search', search.value.trim());
            const response = await fetch('/maintenance/datasets/' + dataset.value + '/features?' + params.toString(), {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'load_failed');
            featureItems = Array.isArray(payload.data) ? payload.data : [];
            feature.innerHTML = '<option value="">اختر المعلم</option>';
            featureItems.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id; option.textContent = item.identifier || ('GIS #' + item.id);
                feature.appendChild(option);
            });
            feature.disabled = featureItems.length === 0;
            status.textContent = featureItems.length ? 'تم تحميل ' + featureItems.length + ' معلم.' : 'لا توجد نتائج لهذا البحث.';
            if (!featureItems.length) renderData(null);
        } catch (error) {
            feature.innerHTML = '<option value="">تعذر تحميل المعالم</option>';
            status.textContent = 'تعذر تحميل المعالم.';
            renderData(null);
        }
    };

    dataset.addEventListener('change', loadFeatures);
    search.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); loadFeatures(); } });
    feature.addEventListener('change', renderSelected);
});
</script>
@endpush
