@extends('layouts.app')

@section('title', 'تفاصيل الشكوى')

@section('content')
<div class="mx-auto max-w-6xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-3"><h2 class="text-xl font-semibold text-ink">تفاصيل الشكوى</h2><code class="ltr-value text-sm text-brand-600">{{ $complaint->complaint_number }}</code></div>
            <p class="mt-1 text-sm text-ink-secondary">{{ $complaint->title }}</p>
        </div>
        <div class="flex gap-2">
            @can('complaints.update')<a href="{{ route('complaints.edit', $complaint) }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">تعديل</a>@endcan
            <a href="{{ route('complaints.index') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">العودة للشكاوى</a>
        </div>
    </div>

    @if(session('success'))<div class="mb-5 rounded-md border border-success bg-success-surface p-4 text-sm text-success">{{ session('success') }}</div>@endif

    <div class="grid gap-6 lg:grid-cols-3">
        <section data-enter class="card-institutional p-6 lg:col-span-2">
            <h3 class="mb-5 text-base font-semibold text-ink">بيانات المشكلة</h3>
            <dl class="grid gap-5 md:grid-cols-2">
                <div><dt class="text-xs text-ink-muted">العنوان</dt><dd class="mt-1 text-sm font-medium text-ink">{{ $complaint->title }}</dd></div>
                <div><dt class="text-xs text-ink-muted">الأولوية</dt><dd class="mt-1 text-sm text-ink">{{ ['low'=>'منخفضة','medium'=>'متوسطة','high'=>'عالية','urgent'=>'عاجلة'][$complaint->priority] ?? $complaint->priority }}</dd></div>
                <div><dt class="text-xs text-ink-muted">الحالة</dt><dd class="mt-1 text-sm text-ink">{{ ['open'=>'جديدة','in_progress'=>'قيد المعالجة','resolved'=>'تم الحل','closed'=>'مغلقة','cancelled'=>'ملغاة'][$complaint->status] ?? $complaint->status }}</dd></div>
                <div><dt class="text-xs text-ink-muted">تاريخ التسجيل</dt><dd class="mt-1 text-sm text-ink ltr-value">{{ $complaint->created_at?->format('Y-m-d H:i') }}</dd></div>
                <div class="md:col-span-2"><dt class="text-xs text-ink-muted">الوصف</dt><dd class="mt-1 whitespace-pre-wrap text-sm leading-7 text-ink-secondary">{{ $complaint->description }}</dd></div>
            </dl>
        </section>

        <section data-enter class="card-institutional p-6">
            <h3 class="mb-5 text-base font-semibold text-ink">المتابعة</h3>
            <dl class="space-y-4">
                <div><dt class="text-xs text-ink-muted">المواطن</dt><dd class="mt-1 text-sm text-ink">{{ $complaint->contact_name ?? '—' }}</dd></div>
                <div><dt class="text-xs text-ink-muted">الهاتف</dt><dd class="mt-1 text-sm text-ink ltr-value">{{ $complaint->contact_phone ?? '—' }}</dd></div>
                <div><dt class="text-xs text-ink-muted">المسند إليه</dt><dd class="mt-1 text-sm text-ink">{{ $complaint->assignedTo->name ?? 'غير مسندة' }}</dd></div>
                <div><dt class="text-xs text-ink-muted">سجلها</dt><dd class="mt-1 text-sm text-ink">{{ $complaint->reportedBy->name ?? '—' }}</dd></div>
                <div><dt class="text-xs text-ink-muted">تم الحل</dt><dd class="mt-1 text-sm text-ink ltr-value">{{ $complaint->resolved_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
            </dl>
        </section>

        <section data-enter class="card-institutional p-6 lg:col-span-3">
            <h3 class="mb-5 text-base font-semibold text-ink">الموقع</h3>
            <div class="grid gap-5 md:grid-cols-3">
                <div><dt class="text-xs text-ink-muted">العنوان / الوصف</dt><dd class="mt-1 text-sm text-ink">{{ $complaint->address ?? '—' }}</dd></div>
                <div><dt class="text-xs text-ink-muted">Latitude</dt><dd class="mt-1 text-sm text-ink ltr-value">{{ $complaint->latitude ?? '—' }}</dd></div>
                <div><dt class="text-xs text-ink-muted">Longitude</dt><dd class="mt-1 text-sm text-ink ltr-value">{{ $complaint->longitude ?? '—' }}</dd></div>
            </div>
        </section>

        @if($complaint->workOrders->isNotEmpty())
            <section data-enter class="card-institutional overflow-hidden lg:col-span-3">
                <div class="border-b border-border px-6 py-4"><h3 class="text-base font-semibold text-ink">المهام المرتبطة</h3></div>
                <div class="overflow-x-auto"><table class="table-institutional"><thead><tr><th>رقم المهمة</th><th>العنوان</th><th>الحالة</th><th>الأولوية</th></tr></thead><tbody>@foreach($complaint->workOrders as $workOrder)<tr><td><code class="ltr-value text-sm">{{ $workOrder->work_order_number }}</code></td><td>{{ $workOrder->title }}</td><td>{{ $workOrder->status }}</td><td>{{ $workOrder->priority }}</td></tr>@endforeach</tbody></table></div>
            </section>
        @endif
    </div>
</div>
@endsection
