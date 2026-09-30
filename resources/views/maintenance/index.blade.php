@extends('layouts.app')

@section('title', 'الصيانة')

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div><h2 class="text-xl font-semibold text-ink">طلبات الصيانة</h2><p class="mt-1 text-sm text-ink-secondary">متابعة طلبات صيانة أصول المياه المرتبطة مباشرة بمعالم GIS.</p></div>
        <div class="flex gap-2">
            @can('maintenance.update')<a href="{{ route('maintenance.settings') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink">إعدادات الصيانة</a>@endcan
            @can('maintenance.create')<a href="{{ route('maintenance.create') }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white">طلب صيانة جديد</a>@endcan
        </div>
    </div>
    <form method="GET" class="card-institutional mb-5 grid gap-4 p-4 md:grid-cols-4">
        <div class="md:col-span-2"><label class="mb-1 block text-sm font-medium text-ink">بحث</label><input name="search" value="{{ request('search') }}" class="input-institutional w-full text-sm" placeholder="رقم الطلب أو وصف المشكلة"></div>
        <div><label class="mb-1 block text-sm font-medium text-ink">الحالة</label><select name="status" class="input-institutional w-full"><option value="">كل الحالات</option><option value="new" @selected(request('status')==='new')>جديد</option><option value="assigned" @selected(request('status')==='assigned')>مسند</option><option value="in_progress" @selected(request('status')==='in_progress')>قيد التنفيذ</option><option value="waiting" @selected(request('status')==='waiting')>بانتظار</option><option value="completed" @selected(request('status')==='completed')>مكتمل</option><option value="not_repaired" @selected(request('status')==='not_repaired')>لم يُصلح</option><option value="cancelled" @selected(request('status')==='cancelled')>ملغى</option></select></div>
        <div><label class="mb-1 block text-sm font-medium text-ink">الأولوية</label><select name="priority" class="input-institutional w-full"><option value="">كل الأولويات</option><option value="low" @selected(request('priority')==='low')>منخفضة</option><option value="medium" @selected(request('priority')==='medium')>متوسطة</option><option value="high" @selected(request('priority')==='high')>عالية</option><option value="urgent" @selected(request('priority')==='urgent')>عاجلة</option></select></div>
        <div><label class="mb-1 block text-sm font-medium text-ink">نوع الأصل</label><select name="dataset_id" class="input-institutional w-full"><option value="">كل الأصول</option>@foreach($datasets as $dataset)<option value="{{ $dataset->id }}" @selected((string)request('dataset_id')===(string)$dataset->id)>{{ $dataset->display_name }}</option>@endforeach</select></div>
        <div><label class="mb-1 block text-sm font-medium text-ink">الفني</label><select name="assigned_to" class="input-institutional w-full"><option value="">كل الفنيين</option>@foreach($technicians as $technician)<option value="{{ $technician->id }}" @selected((string)request('assigned_to')===(string)$technician->id)>{{ $technician->name }}</option>@endforeach</select></div>
        <div><label class="mb-1 block text-sm font-medium text-ink">من تاريخ</label><input type="date" name="date_from" value="{{ request('date_from') }}" class="input-institutional w-full"></div>
        <div><label class="mb-1 block text-sm font-medium text-ink">إلى تاريخ</label><input type="date" name="date_to" value="{{ request('date_to') }}" class="input-institutional w-full"></div>
        <div class="md:col-span-4 flex gap-2 border-t border-border pt-4"><button class="rounded-md bg-ink px-4 py-2 text-sm font-medium text-white">تطبيق</button><a href="{{ route('maintenance.index') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm">مسح</a></div>
    </form>
    <div class="card-institutional overflow-hidden"><div class="overflow-x-auto"><table class="table-institutional"><thead><tr><th>رقم الطلب</th><th>الأصل</th><th>المشكلة</th><th>الأولوية</th><th>الحالة</th><th>الفني</th><th>التاريخ</th><th></th></tr></thead><tbody>
    @forelse($requests as $item)
        <tr class="hover:bg-surface-1"><td class="ltr-value font-medium">{{ $item->request_no }}</td><td>{{ $item->gisFeature?->dataset?->display_name ?? '—' }} @if($item->gisFeature?->datasetRecord?->identifier_value)<span class="text-xs text-ink-muted">({{ $item->gisFeature->datasetRecord->identifier_value }})</span>@endif</td><td class="max-w-sm truncate">{{ $item->problem_description }}</td><td>{{ ['low'=>'منخفضة','medium'=>'متوسطة','high'=>'عالية','urgent'=>'عاجلة'][$item->priority] ?? $item->priority }}</td><td>{{ ['new'=>'جديد','assigned'=>'مسند','in_progress'=>'قيد التنفيذ','waiting'=>'بانتظار','completed'=>'مكتمل','not_repaired'=>'لم يُصلح','cancelled'=>'ملغى'][$item->status] ?? $item->status }}</td><td>{{ $item->assignedTo?->name ?? 'غير مسند' }}</td><td class="ltr-value">{{ $item->requested_at?->format('Y-m-d H:i') }}</td><td><a href="{{ route('maintenance.show', $item) }}" class="text-brand-600 font-medium">عرض</a></td></tr>
    @empty
        <tr><td colspan="8" class="py-12 text-center text-sm text-ink-muted">لا توجد طلبات صيانة.</td></tr>
    @endforelse
    </tbody></table></div><div class="p-4">{{ $requests->links() }}</div></div>
</div>
@endsection
