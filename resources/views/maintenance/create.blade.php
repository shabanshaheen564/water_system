@extends('layouts.app')

@section('title', 'طلب صيانة جديد')

@section('content')
<div class="mx-auto max-w-4xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6"><h2 class="text-xl font-semibold text-ink">إنشاء طلب صيانة</h2><p class="mt-1 text-sm text-ink-secondary">اختر نوع الأصل ثم المعلم الفعلي من GIS؛ لا يتم إدخال اسم الطبقة أو رقم الأصل يدويًا.</p></div>
    @if($errors->any())<div class="mb-5 rounded-md border border-danger bg-danger-surface p-4 text-sm text-danger"><ul class="list-disc pr-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('maintenance.store') }}" class="card-institutional grid gap-5 p-6">
        @csrf
        <div class="grid gap-5 md:grid-cols-2">
            <div><label class="mb-1 block text-sm font-medium text-ink">نوع الأصل / الطبقة</label><select id="dataset_id" class="input-institutional w-full" required><option value="">اختر نوع الأصل</option>@foreach($datasets as $dataset)<option value="{{ $dataset->id }}">{{ $dataset->display_name }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm font-medium text-ink">المعلم الفعلي</label><select id="gis_feature_id" name="gis_feature_id" class="input-institutional w-full" required disabled><option value="">اختر نوع الأصل أولًا</option></select></div>
        </div>
        <div class="grid gap-5 md:grid-cols-2">
            <div><label class="mb-1 block text-sm font-medium text-ink">الأولوية</label><select name="priority" class="input-institutional w-full"><option value="medium">متوسطة</option><option value="low">منخفضة</option><option value="high">عالية</option><option value="urgent">عاجلة</option></select></div>
            <div><label class="mb-1 block text-sm font-medium text-ink">الفني المسند إليه</label><select name="assigned_to" class="input-institutional w-full"><option value="">بدون إسناد</option>@foreach($technicians as $technician)<option value="{{ $technician->id }}">{{ $technician->name }}</option>@endforeach</select></div>
        </div>
        <div><label class="mb-1 block text-sm font-medium text-ink">وصف المشكلة <span class="text-danger">*</span></label><textarea name="problem_description" rows="4" class="input-institutional w-full" required>{{ old('problem_description') }}</textarea></div>
        <div><label class="mb-1 block text-sm font-medium text-ink">وصف العطل</label><textarea name="fault_description" rows="3" class="input-institutional w-full">{{ old('fault_description') }}</textarea></div>
        <div><label class="mb-1 block text-sm font-medium text-ink">ملاحظات</label><textarea name="notes" rows="3" class="input-institutional w-full">{{ old('notes') }}</textarea></div>
        <div class="flex gap-2 border-t border-border pt-4"><button class="rounded-md bg-brand-600 px-5 py-2 text-sm font-medium text-white">حفظ الطلب</button><a href="{{ route('maintenance.index') }}" class="rounded-md border border-border-strong bg-white px-5 py-2 text-sm">إلغاء</a></div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dataset = document.getElementById('dataset_id');
    const feature = document.getElementById('gis_feature_id');
    const featureUrl = @json(url('/maintenance/datasets'));
    dataset.addEventListener('change', async () => {
        feature.innerHTML = '<option value="">جاري تحميل المعالم...</option>';
        feature.disabled = true;
        if (!dataset.value) return;
        try {
            const response = await fetch(featureUrl + '/' + dataset.value + '/features?per_page=100', {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
            if (!response.ok) throw new Error('load');
            const payload = await response.json();
            feature.innerHTML = '<option value="">اختر المعلم</option>';
            payload.data.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.identifier || ('GIS #' + item.id);
                feature.appendChild(option);
            });
            feature.disabled = payload.data.length === 0;
        } catch (error) {
            feature.innerHTML = '<option value="">تعذر تحميل المعالم</option>';
        }
    });
});
</script>
@endpush
