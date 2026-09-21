@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">جودة بيانات GIS</h2>
            <div class="text-muted">{{ $dataset['display_name'] }} ({{ $dataset['name'] }})</div>
        </div>
        <a href="{{ route('datasets.show', $dataset['id']) }}" class="btn btn-outline-secondary">العودة للطبقة</a>
    </div>

    <div class="row g-3 mb-4">
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
            <div class="col-md-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">{{ $card[0] }}</div>
                        <div class="fs-3 fw-bold">{{ $card[1] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-bold">تفاصيل أخطاء الجودة</div>
        <div class="card-body p-0">
            @if(count($details))
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
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
                            <tr>
                                <td>{{ $detail['record_id'] ?? '—' }}</td>
                                <td>{{ $detail['feature_id'] ?? '—' }}</td>
                                <td>{{ $detail['type'] }}</td>
                                <td>{{ $detail['field'] ?? '—' }}</td>
                                <td>{{ $detail['message'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4 text-success">لا توجد أخطاء جودة مكتشفة في البيانات.</div>
            @endif
        </div>
    </div>
</div>
@endsection
