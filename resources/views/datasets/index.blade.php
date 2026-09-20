@extends('layouts.app')

@section('title', 'البيانات الجغرافية')

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold leading-[1.5] text-ink">البيانات الجغرافية</h2>
            <p class="mt-1 text-sm text-ink-secondary">عرض وإدارة البيانات الجغرافية في النظام.</p>
        </div>

        @can('datasets.create')
            <div class="flex items-center gap-3">
                <a href="{{ route('datasets.import') }}"
                   class="btn-motion shrink-0 rounded-md border border-brand-600 bg-white px-4 py-2 text-sm font-medium text-brand-600">
                    استيراد GIS
                </a>
                <a href="{{ route('datasets.create') }}"
                   class="btn-motion shrink-0 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white">
                    إضافة بيانات جغرافية
                </a>
            </div>
        @endcan
    </div>

    <div data-enter class="card-institutional overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-institutional">
                <thead>
                    <tr>
                        <th>اسم العرض</th>
                        <th>النوع</th>
                        <th>مكانية</th>
                        <th>نوع الشكل</th>
                        <th>نظام الإحداثيات</th>
                        <th>الحالة</th>
                        <th>السجلات</th>
                        <th>المعالم</th>
                        <th>الحقول</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($datasets as $dataset)
                        <tr class="hover:bg-surface-1">
                            <td>
                                <div class="text-sm font-medium text-ink">{{ $dataset->display_name }}</div>

                                @if($dataset->description)
                                    <div class="max-w-xs truncate text-sm text-ink-muted">
                                        {{ $dataset->description }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                <span class="inline-flex rounded-md border border-border bg-surface-1 px-2 py-1 text-xs font-medium text-ink-secondary">
                                    {{ $dataset->dataset_type === 'official_layer' ? 'طبقة رسمية' : 'جدول إضافي' }}
                                </span>
                            </td>

                            <td>{{ $dataset->is_spatial ? 'نعم' : 'لا' }}</td>
                            <td>{{ $dataset->is_spatial ? $dataset->geometry_type : '—' }}</td>
                            <td>{{ $dataset->srid ?: '—' }}</td>
                            <td>{{ $dataset->is_active ? 'نشطة' : 'غير نشطة' }}</td>
                            <td>{{ number_format($dataset->records_count ?? 0) }}</td>
                            <td>{{ number_format($dataset->features_count ?? 0) }}</td>
                            <td>{{ $dataset->fields_count ?? 0 }}</td>

                            <td>
                                <div class="flex flex-wrap items-center gap-3 text-sm font-medium">
                                    @if($dataset->is_spatial && $dataset->is_active)
                                        <a href="{{ route('map.index') }}?dataset={{ $dataset->id }}"
                                           class="text-brand-600">
                                            عرض على الخريطة
                                        </a>
                                    @endif

                                    <a href="{{ route('datasets.show', $dataset) }}"
                                       class="text-ink-secondary">
                                        عرض
                                    </a>

                                    @if($dataset->is_spatial && $dataset->is_active)
                                        <a href="{{ route('datasets.export.geojson', $dataset) }}"
                                           class="text-ink-secondary">
                                            GeoJSON
                                        </a>
                                        <a href="{{ route('datasets.export.csv', $dataset) }}"
                                           class="text-ink-secondary">
                                            CSV
                                        </a>
                                        <a href="{{ route('datasets.export.shapefile', $dataset) }}"
                                           class="text-ink-secondary">
                                            Shapefile
                                        </a>
                                    @endif

                                    @can('datasets.update')
                                        <a href="{{ route('datasets.edit', $dataset) }}"
                                           class="text-ink-secondary">
                                            تعديل
                                        </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-12 text-center text-sm text-ink-muted">
                                لا توجد بيانات جغرافية مسجلة.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
