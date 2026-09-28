@extends('layouts.app')

@section('title', 'البيانات التشغيلية المرتبطة')

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <a href="{{ route('datasets.records.index', $dataset) }}" class="text-sm text-brand-600">← العودة إلى سجلات {{ $dataset->display_name }}</a>
        <h2 class="mt-3 text-xl font-semibold text-ink">البيانات التشغيلية المرتبطة</h2>
        <p class="mt-1 text-sm text-ink-secondary">السجل: <code dir="ltr">{{ $record->identifier_value ?? $record->id }}</code></p>
    </div>
    @forelse($related as $item)
        <section class="card-institutional mb-6 overflow-hidden">
            <div class="border-b border-border px-6 py-4">
                <h3 class="text-base font-semibold text-ink">{{ $item['relationship']->childDataset->display_name }}</h3>
                <p class="mt-1 text-xs text-ink-muted">
                    {{ $item['relationship']->parentField->display_name }} =
                    <code dir="ltr">{{ $item['value'] ?? '—' }}</code>
                    · {{ $item['records']->count() }} سجل
                </p>
            </div>
            @if($item['records']->count())
                <div class="overflow-x-auto">
                    <table class="table-institutional">
                        <thead><tr>
                            @foreach($item['fields'] as $field)<th>{{ $field->display_name }}</th>@endforeach
                        </tr></thead>
                        <tbody>
                        @foreach($item['records'] as $child)
                            <tr>
                                @foreach($item['fields'] as $field)
                                    <td>{{ $child->values[$field->name] ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-8 text-center text-sm text-ink-muted">لا توجد سجلات مرتبطة بهذا البئر.</div>
            @endif
        </section>
    @empty
        <div class="card-institutional p-10 text-center">
            <p class="text-sm text-ink-muted">لا توجد جداول تشغيلية مرتبطة بهذا السجل حتى الآن.</p>
        </div>
    @endforelse
</div>
@endsection
