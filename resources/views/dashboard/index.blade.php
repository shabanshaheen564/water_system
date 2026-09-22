@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-ink">{{ __('Dashboard') }}</h2>
            <p class="mt-1 text-sm text-ink-secondary">لوحة تشغيلية مختصرة لنظام المياه وبيانات GIS</p>
        </div>
        @canany(['reports.view', 'complaints.view', 'tasks.view'])
            <a href="{{ route('reports.index') }}" class="btn-motion inline-flex items-center justify-center rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">التقارير والإحصائيات</a>
        @endcanany
    </div>

    <section class="mb-8">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-base font-semibold text-ink">المؤشرات التشغيلية</h3>
            <span class="text-xs text-ink-muted">حسب البيانات الحالية في النظام</span>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([
                ['إجمالي الشكاوى', $operationalSummary['complaints']['total']],
                ['شكاوى قيد المعالجة', $operationalSummary['complaints']['in_progress']],
                ['الشكاوى العاجلة', $operationalSummary['complaints']['urgent']],
                ['إجمالي المهام', $operationalSummary['tasks']['total']],
                ['مهام قيد التنفيذ', $operationalSummary['tasks']['in_progress']],
                ['المهام المكتملة', $operationalSummary['tasks']['completed']],
                ['الشكاوى المؤرشفة', $operationalSummary['complaints']['archived']],
                ['المهام المؤرشفة', $operationalSummary['tasks']['archived']],
            ] as [$label, $value])
                <div data-enter class="card-institutional card-interactive p-5">
                    <p class="text-sm font-medium text-ink-secondary">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-semibold text-ink">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card-institutional overflow-hidden">
            <div class="border-b border-border p-4">
                <h3 class="text-base font-semibold text-ink">أداء الشكاوى المؤرشفة</h3>
            </div>
            <div class="grid grid-cols-2 gap-3 p-4">
                <div class="rounded-md border border-border p-4">
                    <p class="text-xs text-ink-muted">متوسط الاستجابة</p>
                    <p class="mt-1 text-lg font-semibold text-ink">{{ $operationalSummary['performance']['archived_complaints']['average_response_time_formatted'] }}</p>
                </div>
                <div class="rounded-md border border-border p-4">
                    <p class="text-xs text-ink-muted">متوسط الحل</p>
                    <p class="mt-1 text-lg font-semibold text-ink">{{ $operationalSummary['performance']['archived_complaints']['average_resolution_time_formatted'] }}</p>
                </div>
            </div>
        </div>

        <div class="card-institutional overflow-hidden">
            <div class="border-b border-border p-4">
                <h3 class="text-base font-semibold text-ink">أداء المهام المؤرشفة</h3>
            </div>
            <div class="grid grid-cols-3 gap-3 p-4">
                <div class="rounded-md border border-border p-4">
                    <p class="text-xs text-ink-muted">متوسط الاستجابة</p>
                    <p class="mt-1 text-lg font-semibold text-ink">{{ $operationalSummary['performance']['archived_work_orders']['average_response_time_formatted'] }}</p>
                </div>
                <div class="rounded-md border border-border p-4">
                    <p class="text-xs text-ink-muted">متوسط التنفيذ</p>
                    <p class="mt-1 text-lg font-semibold text-ink">{{ $operationalSummary['performance']['archived_work_orders']['average_execution_time_formatted'] }}</p>
                </div>
                <div class="rounded-md border border-border p-4">
                    <p class="text-xs text-ink-muted">متوسط الإجمالي</p>
                    <p class="mt-1 text-lg font-semibold text-ink">{{ $operationalSummary['performance']['archived_work_orders']['average_total_time_formatted'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-8">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-base font-semibold text-ink">مؤشرات GIS</h3>
            <a href="{{ route('map.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">فتح الخريطة</a>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([
                ['Datasets', $totalDatasets],
                ['Records', $totalRecords],
                ['Spatial Datasets', $spatialDatasets],
                ['GIS Features', $gisFeatures],
            ] as [$label, $value])
                <div data-enter class="card-institutional card-interactive p-5">
                    <p class="text-sm font-medium text-ink-secondary">{{ __($label) }}</p>
                    <p class="mt-2 text-3xl font-semibold text-ink">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section data-enter class="card-institutional card-interactive overflow-hidden">
            <div class="flex items-center justify-between border-b border-border p-4">
                <h2 class="text-base font-semibold text-ink">{{ __('Recent Datasets') }}</h2>
                <a href="{{ route('datasets.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">{{ __('View All') }}</a>
            </div>

            @if($recentDatasets->isNotEmpty())
                <div>
                    @foreach($recentDatasets as $dataset)
                        <div class="border-b border-border p-4 last:border-b-0 hover:bg-surface-1">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-ink">{{ $dataset->display_name }}</p>
                                    <p class="ltr-value mt-1 truncate text-xs text-ink-muted">{{ $dataset->name }}</p>
                                </div>
                                <div class="shrink-0 text-end text-xs text-ink-secondary">
                                    <span>{{ number_format($dataset->records_count ?? 0) }} {{ __('records') }}</span>
                                    @if($dataset->features_count > 0)
                                        <span class="ms-1">· {{ number_format($dataset->features_count) }} {{ __('features') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-3 text-sm">
                                <a href="{{ route('datasets.show', $dataset) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ __('View') }}</a>
                                <a href="{{ route('datasets.records.index', $dataset) }}" class="text-ink-secondary hover:text-ink">{{ __('Records') }}</a>
                                @if($dataset->is_spatial && $dataset->is_active)
                                    <a href="{{ route('map.index') }}?dataset={{ $dataset->id }}" class="text-ink-secondary hover:text-ink">{{ __('Map') }}</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-8 text-center">
                    <p class="text-sm text-ink-muted">{{ __('No datasets yet') }}</p>
                    @can('datasets.create')
                        <a href="{{ route('datasets.create') }}" class="btn-motion mt-4 inline-flex rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Create Dataset') }}</a>
                    @endcan
                </div>
            @endif
        </section>

        <section data-enter class="card-institutional card-interactive overflow-hidden">
            <div class="border-b border-border p-4">
                <h2 class="text-base font-semibold text-ink">إجراءات سريعة</h2>
            </div>
            <div class="space-y-3 p-4">
                @canany(['reports.view', 'complaints.view', 'tasks.view'])
                    <a href="{{ route('reports.index') }}" class="flex items-center gap-3 rounded-md border border-border p-3 hover:bg-surface-1">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-brand-50 text-brand-600" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 19V5m0 14h16M8 16v-5m4 5V8m4 8V6"/></svg>
                        </span>
                        <span><span class="block text-sm font-medium text-ink">التقارير والإحصائيات</span><span class="block text-xs text-ink-muted">تحليل الشكاوى والمهام وتصدير النتائج</span></span>
                    </a>
                @endcanany
                @can('datasets.create')
                    <a href="{{ route('datasets.create') }}" class="flex items-center gap-3 rounded-md border border-border p-3 hover:bg-surface-1">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-brand-50 text-brand-600" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 4v16m8-8H4"/></svg>
                        </span>
                        <span><span class="block text-sm font-medium text-ink">{{ __('Create New Dataset') }}</span><span class="block text-xs text-ink-muted">{{ __('Add a new dataset to the system') }}</span></span>
                    </a>
                @endcan
                @if($spatialDatasets > 0)
                    <a href="{{ route('map.index') }}" class="flex items-center gap-3 rounded-md border border-border p-3 hover:bg-surface-1">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-brand-50 text-brand-600" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 2l7 4v6c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V6l7-4z"/></svg>
                        </span>
                        <span><span class="block text-sm font-medium text-ink">{{ __('Open GIS Map') }}</span><span class="block text-xs text-ink-muted">{{ __('Visualize spatial data on the map') }}</span></span>
                    </a>
                @endif
                <a href="{{ route('datasets.index') }}" class="flex items-center gap-3 rounded-md border border-border p-3 hover:bg-surface-1">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-surface-1 text-ink-secondary" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 5h16M4 12h16M4 19h16"/></svg>
                    </span>
                    <span><span class="block text-sm font-medium text-ink">{{ __('Browse All Datasets') }}</span><span class="block text-xs text-ink-muted">{{ __('View and manage all datasets') }}</span></span>
                </a>
            </div>
        </section>
    </div>

    <section data-enter class="card-institutional card-interactive overflow-hidden">
        <div class="border-b border-border p-4">
            <h2 class="text-base font-semibold text-ink">{{ __('System Status') }}</h2>
        </div>
        <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-3">
            @foreach([
                ['Database', $systemStatus['database']],
                ['API', $systemStatus['api']],
            ] as [$label, $status])
                <div class="flex items-center gap-3 rounded-md border border-border p-3">
                    <span class="h-2 w-2 shrink-0 rounded-full bg-success" aria-hidden="true"></span>
                    <div>
                        <p class="text-sm font-medium text-ink">{{ __($label) }}</p>
                        <p class="text-xs text-success">{{ $status === 'online' ? __('Online') : $status }}</p>
                    </div>
                </div>
            @endforeach
            <div class="flex items-center gap-3 rounded-md border border-border p-3">
                <span class="h-2 w-2 shrink-0 rounded-full {{ $systemStatus['gis'] === 'available' ? 'bg-success' : 'bg-warning' }}" aria-hidden="true"></span>
                <div>
                    <p class="text-sm font-medium text-ink">GIS</p>
                    @if($systemStatus['gis'] === 'available')
                        <p class="text-xs text-success">{{ $spatialDatasets }} spatial datasets available</p>
                    @else
                        <p class="text-xs text-warning">No spatial data configured</p>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
