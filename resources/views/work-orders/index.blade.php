@extends('layouts.app')

@section('title', 'المهام')

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div><h2 class="text-xl font-semibold text-ink">المهام</h2><p class="mt-1 text-sm text-ink-secondary">متابعة مهام العمل وإسنادها وربطها بالشكاوى ذات المشكلة نفسها.</p></div>
        <div class="flex flex-wrap items-center gap-2">
            @canany(['reports.export','tasks.export'])
                <details class="relative">
                    <summary class="cursor-pointer list-none rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">تصدير</summary>
                    <div class="absolute left-0 z-20 mt-2 w-36 overflow-hidden rounded-md border border-border bg-white shadow-lg">
                        <a href="{{ route('reports.work-orders.export', request()->query()) }}" class="block px-3 py-2 text-sm text-ink hover:bg-surface-1">Excel</a>
                        <a href="{{ route('reports.work-orders.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="block px-3 py-2 text-sm text-ink hover:bg-surface-1">CSV</a>
                    </div>
                </details>
            @endcan
            @can('tasks.create')<a href="{{ route('work-orders.create') }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">إنشاء مهمة</a>@endcan
        </div>
    </div>

    <form method="GET" class="card-institutional mb-5 grid gap-4 p-4 md:grid-cols-4">
        <div class="md:col-span-2"><label for="search" class="mb-1 block text-sm font-medium text-ink">بحث</label><input id="search" name="search" value="{{ $search }}" class="input-institutional w-full text-sm" placeholder="رقم المهمة، العنوان، المسؤول أو رقم الشكوى"></div>
        <div><label for="status" class="mb-1 block text-sm font-medium text-ink">الحالة</label><select id="status" name="status" class="input-institutional w-full text-sm"><option value="">كل الحالات</option><option value="pending" @selected($status === 'pending')>معلقة</option><option value="assigned" @selected($status === 'assigned')>مسندة</option><option value="in_progress" @selected($status === 'in_progress')>قيد التنفيذ</option><option value="completed" @selected($status === 'completed')>مكتملة</option><option value="cancelled" @selected($status === 'cancelled')>ملغاة</option></select></div>
        <div><label for="priority" class="mb-1 block text-sm font-medium text-ink">الأولوية</label><select id="priority" name="priority" class="input-institutional w-full text-sm"><option value="">كل الأولويات</option><option value="low" @selected($priority === 'low')>منخفضة</option><option value="medium" @selected($priority === 'medium')>متوسطة</option><option value="high" @selected($priority === 'high')>عالية</option><option value="urgent" @selected($priority === 'urgent')>عاجلة</option></select></div>
        <div><label for="assigned_to" class="mb-1 block text-sm font-medium text-ink">المسند إليه</label><select id="assigned_to" name="assigned_to" class="input-institutional w-full text-sm"><option value="">كل المستخدمين</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string) $assignedTo === (string) $user->id)>{{ $user->name }}</option>@endforeach</select></div>
        <div><label for="date_from" class="mb-1 block text-sm font-medium text-ink">من تاريخ</label><input id="date_from" name="date_from" type="date" value="{{ $dateFrom ?? request('date_from') }}" class="input-institutional w-full text-sm"></div>
        <div><label for="date_to" class="mb-1 block text-sm font-medium text-ink">إلى تاريخ</label><input id="date_to" name="date_to" type="date" value="{{ $dateTo ?? request('date_to') }}" class="input-institutional w-full text-sm"></div>
        <div class="md:col-span-4 flex gap-2 border-t border-border pt-4"><button class="rounded-md bg-ink px-4 py-2 text-sm font-medium text-white">تطبيق البحث</button><a href="{{ route('work-orders.index') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">مسح</a></div>
    </form>

    @php($statusLabels = ['pending'=>'معلقة','assigned'=>'مسندة','in_progress'=>'قيد التنفيذ','completed'=>'مكتملة','cancelled'=>'ملغاة'])
    @php($priorityLabels = ['low'=>'منخفضة','medium'=>'متوسطة','high'=>'عالية','urgent'=>'عاجلة'])
    @php($priorityClasses = ['low'=>'border-border bg-surface-1 text-ink-secondary','medium'=>'border-info bg-info-surface text-info','high'=>'border-warning bg-warning-surface text-warning','urgent'=>'border-danger bg-danger-surface text-danger'])

    <div data-enter class="card-institutional overflow-hidden"><div class="overflow-x-auto"><table class="table-institutional"><thead><tr><th>رقم المهمة</th><th>العنوان</th><th>المسند إليه</th><th>الحالة</th><th>الأولوية</th><th>الشكاوى</th><th>تاريخ الإنشاء</th><th>الإجراءات</th></tr></thead><tbody>
    @forelse($workOrders as $workOrder)
        <tr class="hover:bg-surface-1"><td class="whitespace-nowrap"><code class="ltr-value text-sm font-medium text-ink">{{ $workOrder->work_order_number }}</code></td><td><div class="max-w-xs truncate text-sm font-medium text-ink">{{ $workOrder->title }}</div></td><td class="whitespace-nowrap text-sm text-ink-secondary">{{ $workOrder->assignedTo->name ?? 'غير مسندة' }}</td><td class="whitespace-nowrap"><span class="inline-flex rounded-md border border-border bg-surface-1 px-2 py-1 text-xs font-medium text-ink-secondary">{{ $statusLabels[$workOrder->status] ?? $workOrder->status }}</span></td><td class="whitespace-nowrap"><span class="inline-flex rounded-md border px-2 py-1 text-xs font-medium {{ $priorityClasses[$workOrder->priority] ?? $priorityClasses['medium'] }}">{{ $priorityLabels[$workOrder->priority] ?? $workOrder->priority }}</span></td><td class="whitespace-nowrap text-sm text-ink-secondary">{{ $workOrder->complaints_count }}</td><td class="whitespace-nowrap text-sm text-ink-secondary ltr-value">{{ $workOrder->created_at?->format('Y-m-d H:i') }}</td><td class="whitespace-nowrap"><a href="{{ route('work-orders.show', $workOrder) }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">عرض التفاصيل</a></td></tr>
    @empty
        <tr><td colspan="8" class="py-12 text-center text-sm text-ink-muted">لا توجد مهام مسجلة.</td></tr>
    @endforelse
    </tbody></table></div>{{ $workOrders->links() }}</div>
</div>
@endsection
