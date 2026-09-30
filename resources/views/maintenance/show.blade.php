@extends('layouts.app')

@section('title', 'تفاصيل طلب الصيانة')

@section('content')
<div class="mx-auto max-w-6xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-xl font-semibold text-ink">طلب {{ $maintenanceRequest->request_no }}</h2><p class="mt-1 text-sm text-ink-secondary">تفاصيل الأصل والطلب وسجل التنفيذ.</p></div><div class="flex gap-2">@can('maintenance.update')<a href="{{ route('maintenance.edit', $maintenanceRequest) }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink">تعديل</a>@endcan<a href="{{ route('maintenance.index') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm">رجوع</a></div></div>
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card-institutional p-5 lg:col-span-2"><h3 class="mb-4 font-semibold text-ink">بيانات الأصل والطلب</h3><dl class="grid gap-4 md:grid-cols-2 text-sm">
            <div><dt class="text-ink-muted">نوع الأصل</dt><dd class="mt-1 font-medium">{{ $maintenanceRequest->gisFeature?->dataset?->display_name ?? 'لم يعد متاحًا' }}</dd></div>
            <div><dt class="text-ink-muted">معرّف المعلم</dt><dd class="mt-1 font-medium ltr-value">{{ $maintenanceRequest->gisFeature?->datasetRecord?->identifier_value ?? ($maintenanceRequest->gis_feature_id ? 'GIS #'.$maintenanceRequest->gis_feature_id : '—') }}</dd></div>
            <div><dt class="text-ink-muted">الأولوية</dt><dd class="mt-1">{{ ['low'=>'منخفضة','medium'=>'متوسطة','high'=>'عالية','urgent'=>'عاجلة'][$maintenanceRequest->priority] ?? $maintenanceRequest->priority }}</dd></div>
            <div><dt class="text-ink-muted">الحالة</dt><dd class="mt-1">{{ ['new'=>'جديد','assigned'=>'مسند','in_progress'=>'قيد التنفيذ','waiting'=>'بانتظار','completed'=>'مكتمل','not_repaired'=>'لم يُصلح','cancelled'=>'ملغى'][$maintenanceRequest->status] ?? $maintenanceRequest->status }}</dd></div>
            <div><dt class="text-ink-muted">المبلّغ</dt><dd class="mt-1">{{ $maintenanceRequest->reportedBy?->name ?? '—' }}</dd></div>
            <div><dt class="text-ink-muted">الفني</dt><dd class="mt-1">{{ $maintenanceRequest->assignedTo?->name ?? 'غير مسند' }}</dd></div>
            <div class="md:col-span-2"><dt class="text-ink-muted">وصف المشكلة</dt><dd class="mt-1 whitespace-pre-line">{{ $maintenanceRequest->problem_description }}</dd></div>
            <div class="md:col-span-2"><dt class="text-ink-muted">وصف العطل</dt><dd class="mt-1 whitespace-pre-line">{{ $maintenanceRequest->fault_description ?: '—' }}</dd></div>
            <div class="md:col-span-2"><dt class="text-ink-muted">ملاحظات</dt><dd class="mt-1 whitespace-pre-line">{{ $maintenanceRequest->notes ?: '—' }}</dd></div>
        </dl></section>
        <section class="card-institutional p-5"><h3 class="mb-4 font-semibold text-ink">تسجيل التنفيذ</h3>@can('maintenance.complete')
            <form method="POST" action="{{ route('maintenance.jobs.store', $maintenanceRequest) }}" class="grid gap-4">
                @csrf
                <div><label class="mb-1 block text-sm font-medium">الفني</label><select name="technician_id" class="input-institutional w-full"><option value="">الفني الحالي</option>@foreach($technicians as $technician)<option value="{{ $technician->id }}">{{ $technician->name }}</option>@endforeach</select></div>
                <div><label class="mb-1 block text-sm font-medium">العطل المشخّص</label><textarea name="diagnosed_fault" rows="3" class="input-institutional w-full"></textarea></div>
                <div><label class="mb-1 block text-sm font-medium">إجراء الإصلاح</label><textarea name="repair_action" rows="3" class="input-institutional w-full"></textarea></div>
                <div><label class="mb-1 block text-sm font-medium">المواد المستخدمة</label><textarea name="materials_used" rows="2" class="input-institutional w-full"></textarea></div>
                <div><label class="mb-1 block text-sm font-medium">النتيجة</label><select name="result" class="input-institutional w-full"><option value="repaired">تم الإصلاح</option><option value="not_repaired">لم يتم الإصلاح</option><option value="inspection_only">فحص فقط</option></select></div>
                <div><label class="mb-1 block text-sm font-medium">ملاحظات التنفيذ</label><textarea name="notes" rows="2" class="input-institutional w-full"></textarea></div>
                <button class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white">حفظ التنفيذ</button>
            </form>
        @else <p class="text-sm text-ink-muted">لا تملك صلاحية تسجيل التنفيذ.</p>@endcan</section>
    </div>
    <section class="card-institutional mt-5 overflow-hidden"><div class="border-b border-border px-5 py-4"><h3 class="font-semibold text-ink">سجل محاولات التنفيذ</h3></div><div class="overflow-x-auto"><table class="table-institutional"><thead><tr><th>الفني</th><th>البداية</th><th>النهاية</th><th>النتيجة</th><th>العطل</th><th>الإجراء</th><th>المواد</th></tr></thead><tbody>
    @forelse($maintenanceRequest->jobs as $job)
        <tr><td>{{ $job->technician?->name ?? '—' }}</td><td class="ltr-value">{{ $job->started_at?->format('Y-m-d H:i') }}</td><td class="ltr-value">{{ $job->completed_at?->format('Y-m-d H:i') }}</td><td>{{ ['repaired'=>'تم الإصلاح','not_repaired'=>'لم يتم الإصلاح','inspection_only'=>'فحص فقط'][$job->result] ?? $job->result }}</td><td class="max-w-xs">{{ $job->diagnosed_fault ?: '—' }}</td><td class="max-w-xs">{{ $job->repair_action ?: '—' }}</td><td class="max-w-xs">{{ $job->materials_used ?: '—' }}</td></tr>
    @empty
        <tr><td colspan="7" class="py-8 text-center text-sm text-ink-muted">لا توجد عمليات تنفيذ مسجلة بعد.</td></tr>
    @endforelse
    </tbody></table></div></section>
</div>
@endsection
