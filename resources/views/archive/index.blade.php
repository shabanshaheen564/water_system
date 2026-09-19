@extends('layouts.app')

@section('title', 'أرشيف الشكاوى والمهام')

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold text-ink">أرشيف الشكاوى والمهام</h2>
            <p class="mt-1 text-sm text-ink-secondary">السجلات المكتملة مع مؤشرات زمن الاستجابة والتنفيذ والحل.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('complaints.index') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">الشكاوى الحالية</a>
            <a href="{{ route('work-orders.index') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">المهام الحالية</a>
        </div>
    </div>

    <form method="GET" class="card-institutional mb-5 grid gap-4 p-4 md:grid-cols-4">
        <div><label class="mb-1 block text-sm font-medium text-ink">من تاريخ الأرشفة</label><input name="date_from" type="date" value="{{ $dateFrom }}" class="input-institutional w-full text-sm"></div>
        <div><label class="mb-1 block text-sm font-medium text-ink">إلى تاريخ الأرشفة</label><input name="date_to" type="date" value="{{ $dateTo }}" class="input-institutional w-full text-sm"></div>
        <div><label class="mb-1 block text-sm font-medium text-ink">العرض</label><select name="show" class="input-institutional w-full text-sm"><option value="all" @selected($show === 'all')>الشكاوى والمهام</option><option value="complaints" @selected($show === 'complaints')>الشكاوى فقط</option><option value="work_orders" @selected($show === 'work_orders')>المهام فقط</option></select></div>
        <div class="flex items-end gap-2"><button class="rounded-md bg-ink px-4 py-2 text-sm font-medium text-white">تطبيق</button><a href="{{ route('archive.index') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink">مسح</a></div>
    </form>

    <div class="mb-6 grid gap-4 md:grid-cols-3 lg:grid-cols-5">
        @foreach([
            ['label'=>'شكاوى مؤرشفة','value'=>$stats['complaints_count']],
            ['label'=>'مهام مؤرشفة','value'=>$stats['work_orders_count']],
            ['label'=>'متوسط الاستجابة','value'=>$stats['avg_response_minutes'] !== null ? round($stats['avg_response_minutes']).' دقيقة' : '—'],
            ['label'=>'متوسط زمن الحل','value'=>$stats['avg_resolution_minutes'] !== null ? round($stats['avg_resolution_minutes']).' دقيقة' : '—'],
            ['label'=>'متوسط تنفيذ المهمة','value'=>$stats['avg_task_execution_minutes'] !== null ? round($stats['avg_task_execution_minutes']).' دقيقة' : '—'],
        ] as $card)
            <div class="card-institutional p-4"><div class="text-xs text-ink-muted">{{ $card['label'] }}</div><div class="mt-2 text-2xl font-semibold text-ink">{{ $card['value'] }}</div></div>
        @endforeach
    </div>

    @if($show !== 'work_orders')
    <div class="card-institutional mb-6 overflow-hidden">
        <div class="border-b border-border px-4 py-3 font-semibold text-ink">الشكاوى المؤرشفة</div>
        <div class="overflow-x-auto"><table class="table-institutional"><thead><tr><th>رقم الشكوى</th><th>العنوان</th><th>الأولوية</th><th>زمن الاستجابة</th><th>زمن الحل</th><th>أرشفت في</th></tr></thead><tbody>
        @forelse($complaints as $item)
            <tr><td class="ltr-value">{{ $item->complaint_number }}</td><td>{{ $item->title }}</td><td>{{ $item->priority }}</td><td>{{ $item->response_time_minutes !== null ? $item->response_time_minutes.' دقيقة' : '—' }}</td><td>{{ $item->resolution_time_minutes !== null ? $item->resolution_time_minutes.' دقيقة' : '—' }}</td><td class="ltr-value">{{ $item->archived_at?->format('Y-m-d H:i') }}</td></tr>
        @empty <tr><td colspan="6" class="py-10 text-center text-sm text-ink-muted">لا توجد سجلات مؤرشفة.</td></tr>@endforelse
        </tbody></table></div>
        {{ $complaints->links() }}
    </div>
    @endif

    @if($show !== 'complaints')
    <div class="card-institutional overflow-hidden">
        <div class="border-b border-border px-4 py-3 font-semibold text-ink">المهام المؤرشفة</div>
        <div class="overflow-x-auto"><table class="table-institutional"><thead><tr><th>رقم المهمة</th><th>العنوان</th><th>الأولوية</th><th>زمن الاستجابة</th><th>زمن التنفيذ</th><th>أرشفت في</th></tr></thead><tbody>
        @forelse($workOrders as $item)
            <tr><td class="ltr-value">{{ $item->work_order_number }}</td><td>{{ $item->title }}</td><td>{{ $item->priority }}</td><td>{{ $item->response_time_minutes !== null ? $item->response_time_minutes.' دقيقة' : '—' }}</td><td>{{ $item->execution_time_minutes !== null ? $item->execution_time_minutes.' دقيقة' : '—' }}</td><td class="ltr-value">{{ $item->archived_at?->format('Y-m-d H:i') }}</td></tr>
        @empty <tr><td colspan="6" class="py-10 text-center text-sm text-ink-muted">لا توجد سجلات مؤرشفة.</td></tr>@endforelse
        </tbody></table></div>
        {{ $workOrders->links() }}
    </div>
    @endif
</div>
@endsection
