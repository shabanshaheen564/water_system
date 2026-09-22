@extends('layouts.app')

@section('title', 'تفاصيل الشكوى')

@section('content')
<div class="mx-auto max-w-6xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div><div class="flex items-center gap-3"><h2 class="text-xl font-semibold text-ink">تفاصيل الشكوى</h2><code class="ltr-value text-sm text-brand-600">{{ $complaint->complaint_number }}</code></div><p class="mt-1 text-sm text-ink-secondary">{{ $complaint->title }}</p></div>
        <div class="flex flex-wrap gap-2">
            @canany(['reports.export','complaints.export'])<a href="{{ route('reports.complaints.pdf', $complaint) }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">تقرير PDF</a>@endcan
            @can('complaints.convert_to_task')
                @if($complaint->workOrders->isEmpty())
                    <a href="{{ route('complaints.convert-to-work-order', $complaint) }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">تحويل إلى مهمة جديدة</a>
                @endif
            @endcan
            @can('complaints.update')
                @can('tasks.update')
                    <a href="{{ route('complaints.add-to-work-order', $complaint) }}" class="rounded-md border border-brand-600 bg-white px-4 py-2 text-sm font-medium text-brand-700 hover:bg-surface-1">إضافة إلى مهمة موجودة</a>
                @endcan
            @endcan
            @canany(['complaints.update', 'complaints.transition'])<a href="{{ route('complaints.edit', $complaint) }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">معالجة / متابعة</a>@endcanany
            @can('complaints.delete')
                @if($complaint->workOrders->isEmpty())
                    <form method="POST" action="{{ route('complaints.destroy', $complaint) }}" onsubmit="return confirm('هل تريد حذف هذه الشكوى نهائيًا؟');">@csrf @method('DELETE')<button type="submit" class="rounded-md border border-danger bg-white px-4 py-2 text-sm font-medium text-danger hover:bg-danger-surface">حذف الشكوى</button></form>
                @endif
            @endcan
            <a href="{{ route('complaints.index') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">العودة للشكاوى</a>
        </div>
    </div>
    @if(session('success'))<div class="mb-5 rounded-md border border-success bg-success-surface p-4 text-sm text-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded-md border border-danger bg-danger-surface p-4 text-sm text-danger">{{ $errors->first() }}</div>@endif
    <div class="grid gap-6 lg:grid-cols-3">
        <section data-enter class="card-institutional p-6 lg:col-span-2"><h3 class="mb-5 text-base font-semibold text-ink">بيانات المشكلة</h3><dl class="grid gap-5 md:grid-cols-2"><div><dt class="text-xs text-ink-muted">العنوان</dt><dd class="mt-1 text-sm font-medium text-ink">{{ $complaint->title }}</dd></div><div><dt class="text-xs text-ink-muted">الأولوية</dt><dd class="mt-1 text-sm text-ink">{{ ['low'=>'منخفضة','medium'=>'متوسطة','high'=>'عالية','urgent'=>'عاجلة'][$complaint->priority] ?? $complaint->priority }}</dd></div><div><dt class="text-xs text-ink-muted">الحالة</dt><dd class="mt-1 text-sm text-ink">{{ ['open'=>'جديدة','in_progress'=>'قيد المعالجة','resolved'=>'تم الحل','closed'=>'مغلقة','cancelled'=>'ملغاة'][$complaint->status] ?? $complaint->status }}</dd></div><div><dt class="text-xs text-ink-muted">تاريخ التسجيل</dt><dd class="mt-1 text-sm text-ink ltr-value">{{ $complaint->created_at?->format('Y-m-d H:i') }}</dd></div><div class="md:col-span-2"><dt class="text-xs text-ink-muted">الوصف</dt><dd class="mt-1 whitespace-pre-wrap text-sm leading-7 text-ink-secondary">{{ $complaint->description }}</dd></div></dl></section>
        <section data-enter class="card-institutional p-6"><h3 class="mb-5 text-base font-semibold text-ink">الإسناد والمتابعة</h3><dl class="space-y-4"><div><dt class="text-xs text-ink-muted">المواطن</dt><dd class="mt-1 text-sm text-ink">{{ $complaint->contact_name ?? '—' }}</dd></div><div><dt class="text-xs text-ink-muted">الهاتف</dt><dd class="mt-1 text-sm text-ink ltr-value">{{ $complaint->contact_phone ?? '—' }}</dd></div><div><dt class="text-xs text-ink-muted">المسند إليه</dt><dd class="mt-1 text-sm font-medium text-ink">{{ $complaint->assignedTo->name ?? 'غير مسندة' }}</dd></div><div><dt class="text-xs text-ink-muted">آخر من عالجها</dt><dd class="mt-1 text-sm text-ink">{{ $complaint->processedBy->name ?? '—' }}</dd></div><div><dt class="text-xs text-ink-muted">وقت المعالجة</dt><dd class="mt-1 text-sm text-ink ltr-value">{{ $complaint->processed_at?->format('Y-m-d H:i') ?? '—' }}</dd></div><div><dt class="text-xs text-ink-muted">تم الحل</dt><dd class="mt-1 text-sm text-ink ltr-value">{{ $complaint->resolved_at?->format('Y-m-d H:i') ?? '—' }}</dd></div></dl></section>
        <section data-enter class="card-institutional p-6 lg:col-span-3"><h3 class="mb-5 text-base font-semibold text-ink">المعالجة والحل</h3><div class="grid gap-6 md:grid-cols-2"><div><dt class="text-xs text-ink-muted">إجراءات المعالجة والملاحظات</dt><dd class="mt-2 whitespace-pre-wrap text-sm leading-7 text-ink-secondary">{{ $complaint->processing_notes ?: 'لا توجد ملاحظات مسجلة.' }}</dd></div><div><dt class="text-xs text-ink-muted">الحل / نتيجة المعالجة</dt><dd class="mt-2 whitespace-pre-wrap text-sm leading-7 text-ink-secondary">{{ $complaint->solution ?: 'لم يتم تسجيل الحل بعد.' }}</dd></div></div></section>
        <section data-enter class="card-institutional p-6 lg:col-span-3"><h3 class="mb-5 text-base font-semibold text-ink">الموقع</h3><div class="grid gap-5 md:grid-cols-3"><div><dt class="text-xs text-ink-muted">العنوان / وصف الموقع</dt><dd class="mt-1 text-sm text-ink">{{ $complaint->address ?? '—' }}</dd></div><div><dt class="text-xs text-ink-muted">Latitude</dt><dd class="mt-1 text-sm text-ink ltr-value">{{ $complaint->latitude ?? '—' }}</dd></div><div><dt class="text-xs text-ink-muted">Longitude</dt><dd class="mt-1 text-sm text-ink ltr-value">{{ $complaint->longitude ?? '—' }}</dd></div></div></section>
        @php $linkedGisIds = collect($gisContext['linked'] ?? [])->pluck('id')->all(); @endphp
        <section data-enter class="card-institutional p-6 lg:col-span-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h3 class="text-base font-semibold text-ink">السياق المكاني</h3><p class="mt-1 text-sm text-ink-secondary">الأصول المكانية المرتبطة بالشكوى وأقرب الأصول التشغيلية إلى موقعها.</p></div>
                @if($complaint->latitude !== null && $complaint->longitude !== null)
                    <span class="text-xs text-ink-muted ltr-value">{{ $complaint->latitude }}, {{ $complaint->longitude }}</span>
                @endif
            </div>
            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                <div>
                    <h4 class="mb-3 text-sm font-semibold text-ink">الأصول المرتبطة</h4>
                    @if(!empty($gisContext['linked']))
                        <div class="space-y-2">
                            @foreach($gisContext['linked'] as $feature)
                                <div class="flex items-center justify-between gap-3 rounded-md border border-border bg-surface-1 p-3">
                                    <div><div class="text-sm font-medium text-ink">{{ $feature['dataset_name'] }}</div><div class="mt-1 text-xs text-ink-muted">{{ $feature['identifier'] ?: 'Feature #'.$feature['id'] }} · {{ $feature['geometry_type'] }}</div></div>
                                    @can('complaints.update')
                                        <form method="POST" action="{{ route('complaints.gis.unlink', [$complaint, $feature['id']]) }}" onsubmit="return confirm('إلغاء ربط هذا الأصل؟');">@csrf @method('DELETE')<button class="text-xs font-medium text-danger">إلغاء الربط</button></form>
                                    @endcan
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="rounded-md border border-dashed border-border p-4 text-sm text-ink-muted">لا توجد أصول مكانية مرتبطة حاليًا.</p>
                    @endif
                </div>
                <div>
                    <h4 class="mb-3 text-sm font-semibold text-ink">أقرب الأصول التشغيلية</h4>
                    @if(!empty($gisContext['nearest']))
                        <div class="space-y-2">
                            @foreach($gisContext['nearest'] as $feature)
                                <div class="flex items-center justify-between gap-3 rounded-md border border-border p-3">
                                    <div><div class="text-sm font-medium text-ink">{{ $feature['dataset_name'] }}</div><div class="mt-1 text-xs text-ink-muted">{{ $feature['identifier'] ?: 'Feature #'.$feature['id'] }} · {{ number_format($feature['distance_m'], 2) }} م</div></div>
                                    @if(in_array($feature['id'], $linkedGisIds, true))
                                        <span class="text-xs font-medium text-success">مرتبط</span>
                                    @else
                                        @can('complaints.update')
                                            <form method="POST" action="{{ route('complaints.gis.link', [$complaint, $feature['id']]) }}">@csrf<button class="rounded-md border border-brand-600 bg-white px-3 py-1.5 text-xs font-medium text-brand-700 hover:bg-surface-1">ربط</button></form>
                                        @endcan
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="rounded-md border border-dashed border-border p-4 text-sm text-ink-muted">لا يوجد موقع جغرافي صالح للبحث عن أقرب أصل.</p>
                    @endif
                </div>
            </div>
        </section>
        @if($complaint->workOrders->isNotEmpty())
            <section data-enter class="card-institutional overflow-hidden lg:col-span-3"><div class="border-b border-border px-6 py-4"><h3 class="text-base font-semibold text-ink">المهام المرتبطة</h3></div><div class="overflow-x-auto"><table class="table-institutional"><thead><tr><th>رقم المهمة</th><th>العنوان</th><th>المسؤول</th><th>الحالة</th><th>الأولوية</th><th>عدد الشكاوى</th></tr></thead><tbody>@foreach($complaint->workOrders as $workOrder)<tr><td><a href="{{ route('work-orders.show', $workOrder) }}" class="ltr-value text-sm font-medium text-brand-600 hover:text-brand-700">{{ $workOrder->work_order_number }}</a></td><td>{{ $workOrder->title }}</td><td>{{ $workOrder->assignedTo->name ?? '—' }}</td><td>{{ ['pending'=>'معلقة','assigned'=>'مسندة','in_progress'=>'قيد التنفيذ','completed'=>'مكتملة','cancelled'=>'ملغاة'][$workOrder->status] ?? $workOrder->status }}</td><td>{{ ['low'=>'منخفضة','medium'=>'متوسطة','high'=>'عالية','urgent'=>'عاجلة'][$workOrder->priority] ?? $workOrder->priority }}</td><td>{{ $workOrder->complaints->count() }}</td></tr>@if($workOrder->complaints->count() > 1)<tr><td colspan="6" class="bg-surface-1"><div class="text-xs text-ink-muted">الشكاوى المرتبطة بهذه المهمة:</div><div class="mt-2 flex flex-wrap gap-2">@foreach($workOrder->complaints as $linkedComplaint)<span class="rounded border border-border bg-white px-2 py-1 text-xs text-ink"><code class="ltr-value text-brand-600">{{ $linkedComplaint->complaint_number }}</code> — {{ $linkedComplaint->title }}</span>@endforeach</div></td></tr>@endif @endforeach</tbody></table></div></section>
        @endif
    </div>
</div>
@endsection
