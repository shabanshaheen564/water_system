@extends('layouts.app')

@section('title', 'GIS Data Quality')

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold text-ink">جودة بيانات GIS</h2>
            <p class="mt-1 text-sm text-ink-muted">{{ $dataset['display_name'] }} <span class="ltr-value">({{ $dataset['name'] }})</span></p>
        </div>
        <a href="{{ route('datasets.show', $dataset['id']) }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">العودة للطبقة</a>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['إجمالي السجلات', $summary['total_records']],
            ['Geometry صالح', $summary['valid_geometry']],
            ['Geometry غير صالح', $summary['invalid_geometry']],
            ['Geometry مفقود', $summary['missing_geometry']],
            ['Geometry فارغ', $summary['empty_geometry']],
            ['أخطاء SRID', $summary['srid_errors']],
            ['أخطاء نوع Geometry', $summary['geometry_type_errors']],
            ['أخطاء Attributes', $summary['attribute_errors']],
            ['تكرار Identifier', $summary['duplicate_identifiers']],
            ['تكرار Geometry', $summary['duplicate_geometries']],
        ] as $card)
            <div class="card-institutional p-5">
                <div class="text-xs font-medium text-ink-muted">{{ $card[0] }}</div>
                <div class="mt-1 text-2xl font-semibold text-ink ltr-value">{{ $card[1] }}</div>
            </div>
        @endforeach
    </div>

    <section class="card-institutional overflow-hidden">
        <div class="border-b border-border px-6 py-4">
            <h3 class="text-base font-semibold text-ink">تفاصيل أخطاء الجودة</h3>
        </div>
        @if(count($details))
            <div class="overflow-x-auto">
                <table class="table-institutional">
                    <thead>
                        <tr>
                            <th>Record</th>
                            <th>Feature</th>
                            <th>النوع</th>
                            <th>الحقل</th>
                            <th>التفاصيل</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($details as $detail)
                        <tr class="hover:bg-surface-1">
                            <td class="ltr-value">{{ $detail['record_id'] ?? '—' }}</td>
                            <td class="ltr-value">{{ $detail['feature_id'] ?? '—' }}</td>
                            <td><code class="ltr-value text-xs text-ink-secondary">{{ $detail['type'] }}</code></td>
                            <td>{{ $detail['field'] ?? '—' }}</td>
                            <td>{{ $detail['message'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-8 text-center text-sm text-ink-muted">لا توجد أخطاء جودة مكتشفة في البيانات.</div>
        @endif
    </section>
</div>
@endsection
