@extends('layouts.app')

@section('title', 'التقارير والإحصائيات')

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-ink">التقارير والإحصائيات</h2>
        <p class="mt-1 text-sm text-ink-secondary">تصفية وتحليل الشكاوى والمهام حسب الفترة والحالة والأولوية والموظف.</p>
    </div>

    <form method="GET" action="{{ route('reports.index') }}" class="mb-8 card-institutional p-5">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">من تاريخ</label>
                <input type="date" name="date_from" value="{{ $requestFilters['date_from'] ?? '' }}" class="w-full rounded-md border border-border px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">إلى تاريخ</label>
                <input type="date" name="date_to" value="{{ $requestFilters['date_to'] ?? '' }}" class="w-full rounded-md border border-border px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">حالة الشكوى</label>
                <select name="complaint_status" class="w-full rounded-md border border-border px-3 py-2 text-sm">
                    <option value="">الكل</option>
                    @foreach($filters['complaint_statuses'] as $item)
                        <option value="{{ $item['value'] }}" @selected(($requestFilters['complaint_status'] ?? '') === $item['value'])>{{ $item['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">أولوية الشكوى</label>
                <select name="complaint_priority" class="w-full rounded-md border border-border px-3 py-2 text-sm">
                    <option value="">الكل</option>
                    @foreach($filters['priorities'] as $item)
                        <option value="{{ $item['value'] }}" @selected(($requestFilters['complaint_priority'] ?? '') === $item['value'])>{{ $item['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">حالة المهمة</label>
                <select name="task_status" class="w-full rounded-md border border-border px-3 py-2 text-sm">
                    <option value="">الكل</option>
                    @foreach($filters['work_order_statuses'] as $item)
                        <option value="{{ $item['value'] }}" @selected(($requestFilters['task_status'] ?? '') === $item['value'])>{{ $item['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">أولوية المهمة</label>
                <select name="task_priority" class="w-full rounded-md border border-border px-3 py-2 text-sm">
                    <option value="">الكل</option>
                    @foreach($filters['priorities'] as $item)
                        <option value="{{ $item['value'] }}" @selected(($requestFilters['task_priority'] ?? '') === $item['value'])>{{ $item['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">الموظف المسؤول عن الشكوى</label>
                <select name="complaint_assigned_to" class="w-full rounded-md border border-border px-3 py-2 text-sm">
                    <option value="">الكل</option>
                    @foreach($filters['users'] as $user)
                        <option value="{{ $user->id }}" @selected((string)($requestFilters['complaint_assigned_to'] ?? '') === (string)$user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">الموظف المسؤول عن المهمة</label>
                <select name="task_assigned_to" class="w-full rounded-md border border-border px-3 py-2 text-sm">
                    <option value="">الكل</option>
                    @foreach($filters['users'] as $user)
                        <option value="{{ $user->id }}" @selected((string)($requestFilters['task_assigned_to'] ?? '') === (string)$user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="submit" class="btn-motion rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">تطبيق الفلاتر</button>
            <a href="{{ route('reports.index') }}" class="rounded-md border border-border px-4 py-2 text-sm font-medium text-ink-secondary hover:bg-surface-1">إعادة ضبط</a>
        </div>
    </form>

    <section class="mb-8">
        <h3 class="mb-3 text-base font-semibold text-ink">إحصائيات الشكاوى</h3>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach([
                ['الإجمالي', $summary['complaints']['total']],
                ['جديدة', $summary['complaints']['open']],
                ['قيد المعالجة', $summary['complaints']['in_progress']],
                ['تم الحل', $summary['complaints']['resolved']],
                ['مغلقة', $summary['complaints']['closed']],
                ['ملغاة', $summary['complaints']['cancelled']],
                ['عاجلة', $summary['complaints']['urgent']],
                ['مؤرشفة', $summary['complaints']['archived']],
            ] as [$label, $value])
                <div class="card-institutional p-4">
                    <p class="text-xs text-ink-secondary">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold text-ink">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mb-8">
        <h3 class="mb-3 text-base font-semibold text-ink">إحصائيات المهام</h3>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach([
                ['الإجمالي', $summary['tasks']['total']],
                ['معلقة', $summary['tasks']['pending']],
                ['مسندة', $summary['tasks']['assigned']],
                ['قيد التنفيذ', $summary['tasks']['in_progress']],
                ['مكتملة', $summary['tasks']['completed']],
                ['ملغاة', $summary['tasks']['cancelled']],
                ['عاجلة', $summary['tasks']['urgent']],
                ['مؤرشفة', $summary['tasks']['archived']],
            ] as [$label, $value])
                <div class="card-institutional p-4">
                    <p class="text-xs text-ink-secondary">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold text-ink">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card-institutional p-5">
            <h3 class="mb-4 text-base font-semibold text-ink">أداء الشكاوى المؤرشفة</h3>
            <div class="grid grid-cols-2 gap-3">
                <div><p class="text-xs text-ink-muted">متوسط الاستجابة</p><p class="mt-1 font-semibold">{{ $summary['performance']['archived_complaints']['average_response_time_formatted'] }}</p></div>
                <div><p class="text-xs text-ink-muted">متوسط الحل</p><p class="mt-1 font-semibold">{{ $summary['performance']['archived_complaints']['average_resolution_time_formatted'] }}</p></div>
                <div><p class="text-xs text-ink-muted">أسرع استجابة</p><p class="mt-1 font-semibold">{{ $summary['performance']['archived_complaints']['fastest_response_minutes'] !== null ? $summary['performance']['archived_complaints']['fastest_response_minutes'].' دقيقة' : '—' }}</p></div>
                <div><p class="text-xs text-ink-muted">أبطأ استجابة</p><p class="mt-1 font-semibold">{{ $summary['performance']['archived_complaints']['slowest_response_minutes'] !== null ? $summary['performance']['archived_complaints']['slowest_response_minutes'].' دقيقة' : '—' }}</p></div>
            </div>
        </div>
        <div class="card-institutional p-5">
            <h3 class="mb-4 text-base font-semibold text-ink">أداء المهام المؤرشفة</h3>
            <div class="grid grid-cols-3 gap-3">
                <div><p class="text-xs text-ink-muted">متوسط الاستجابة</p><p class="mt-1 font-semibold">{{ $summary['performance']['archived_work_orders']['average_response_time_formatted'] }}</p></div>
                <div><p class="text-xs text-ink-muted">متوسط التنفيذ</p><p class="mt-1 font-semibold">{{ $summary['performance']['archived_work_orders']['average_execution_time_formatted'] }}</p></div>
                <div><p class="text-xs text-ink-muted">متوسط الإجمالي</p><p class="mt-1 font-semibold">{{ $summary['performance']['archived_work_orders']['average_total_time_formatted'] }}</p></div>
            </div>
        </div>
    </section>

    <section class="card-institutional p-5">
        <h3 class="mb-4 text-base font-semibold text-ink">التصدير</h3>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('reports.complaints.export', array_merge($requestFilters, ['format' => 'xlsx'])) }}" class="rounded-md border border-border px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">تصدير الشكاوى XLSX</a>
            <a href="{{ route('reports.complaints.export', array_merge($requestFilters, ['format' => 'csv'])) }}" class="rounded-md border border-border px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">تصدير الشكاوى CSV</a>
            <a href="{{ route('reports.work-orders.export', array_merge($requestFilters, ['format' => 'xlsx'])) }}" class="rounded-md border border-border px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">تصدير المهام XLSX</a>
            <a href="{{ route('reports.work-orders.export', array_merge($requestFilters, ['format' => 'csv'])) }}" class="rounded-md border border-border px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">تصدير المهام CSV</a>
        </div>
    </section>
</div>
@endsection
