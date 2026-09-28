@extends('layouts.app')

@section('title', 'مراجعة الجدول التشغيلي')

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-ink">مراجعة بيانات الجدول التشغيلي</h2>
        <p class="mt-1 text-sm text-ink-secondary">راجع أسماء الأعمدة وأنواعها، ويمكنك ربط الجدول بالبئر مباشرة قبل الاستيراد.</p>
    </div>

    @if(!empty($preview['parse_errors']))
        <div class="mb-6 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            يوجد {{ count($preview['parse_errors']) }} صف لم تتم قراءته بشكل صحيح. سيتم الاحتفاظ بها في سجل الاستيراد.
        </div>
    @endif

    <form method="POST" action="{{ route('datasets.operational-import.confirm') }}" class="space-y-6">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <section class="card-institutional p-6">
            <h3 class="mb-4 text-base font-semibold text-ink">معلومات الجدول</h3>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">اسم النظام</label>
                    <input name="name" required value="{{ old('name', \\Illuminate\\Support\\Str::snake(pathinfo($state['original_filename'] ?? 'operational_table', PATHINFO_FILENAME))) }}" class="input-institutional w-full px-3 py-2" dir="ltr">
                    @error('name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">اسم العرض</label>
                    <input name="display_name" required value="{{ old('display_name', pathinfo($state['original_filename'] ?? 'Operational Table', PATHINFO_FILENAME)) }}" class="input-institutional w-full px-3 py-2">
                    @error('display_name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">مصدر البيانات</label>
                    <input name="source_name" value="{{ old('source_name') }}" class="input-institutional w-full px-3 py-2" placeholder="Water Department / Laboratory">
                </div>
            </div>
            <div class="mt-5">
                <label class="mb-1 block text-sm font-medium text-ink">الوصف</label>
                <textarea name="description" rows="2" class="input-institutional w-full px-3 py-2">{{ old('description') }}</textarea>
            </div>
        </section>

        <section class="card-institutional overflow-hidden">
            <div class="border-b border-border px-6 py-4">
                <h3 class="text-base font-semibold text-ink">الأعمدة المكتشفة ({{ count($preview['headers']) }})</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table-institutional">
                    <thead><tr><th>عمود Excel</th><th>نوع البيانات</th><th>معاينة</th></tr></thead>
                    <tbody>
                    @foreach($preview['headers'] as $header)
                        <tr>
                            <td><code dir="ltr" class="text-sm">{{ $header }}</code></td>
                            <td>
                                <select name="field_types[{{ $header }}]" class="input-institutional min-w-40 px-3 py-2 text-sm" dir="ltr">
                                    @foreach(['string','text','integer','decimal','boolean','date','datetime'] as $type)
                                        <option value="{{ $type }}" {{ old("field_types.$header", $preview['inferred_types'][$header]) === $type ? 'selected' : '' }}>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="max-w-md text-xs text-ink-secondary">
                                @foreach(array_slice($preview['sample_rows'], 0, 3) as $row)
                                    <span class="me-2 inline-block rounded bg-surface-1 px-2 py-1" dir="ltr">{{ \\Illuminate\\Support\\Str::limit((string)($row[$header] ?? '—'), 35) }}</span>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card-institutional p-6">
            <h3 class="mb-2 text-base font-semibold text-ink">ربط الجدول بطبقة الآبار (اختياري)</h3>
            <p class="mb-5 text-sm text-ink-secondary">إذا اخترت الربط هنا، يجب أن يكون حقل البئر Identifier أو Unique، ويجب أن تتطابق القيم الموجودة في الملف مع قيم الآبار.</p>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">الطبقة الأساسية</label>
                    <select name="parent_dataset_id" id="parent_dataset_id" class="input-institutional w-full px-3 py-2">
                        <option value="">بدون ربط الآن</option>
                        @foreach($datasets as $candidate)
                            <option value="{{ $candidate->id }}" {{ old('parent_dataset_id') == $candidate->id ? 'selected' : '' }}>{{ $candidate->display_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">حقل البئر</label>
                    <select name="parent_field_id" id="parent_field_id" class="input-institutional w-full px-3 py-2">
                        <option value="">اختر الطبقة أولاً</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">حقل الربط في الملف</label>
                    <select name="child_field" class="input-institutional w-full px-3 py-2">
                        <option value="">بدون ربط الآن</option>
                        @foreach($preview['headers'] as $header)
                            <option value="{{ $header }}" {{ old('child_field') === $header ? 'selected' : '' }}>{{ $header }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @error('parent_dataset_id')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
            @error('parent_field_id')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
            @error('child_field')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror

            <div class="mt-5 rounded-md border border-border bg-surface-1 p-4 text-sm text-ink-secondary">
                <strong>المقصود:</strong> إذا كان الملف يحتوي <code dir="ltr">Well_id</code> وكانت طبقة الآبار تحتوي نفس القيمة، يصبح الجدول تابعاً للبئر ويمكن الوصول إلى بياناته من صفحة البئر لاحقاً.
            </div>
        </section>

        <section class="card-institutional overflow-hidden">
            <div class="border-b border-border px-6 py-4">
                <h3 class="text-base font-semibold text-ink">معاينة أولية — {{ $preview['total_rows'] }} صف</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table-institutional">
                    <thead><tr>@foreach($preview['headers'] as $header)<th dir="ltr">{{ $header }}</th>@endforeach</tr></thead>
                    <tbody>
                    @forelse($preview['sample_rows'] as $row)
                        <tr>@foreach($preview['headers'] as $header)<td dir="ltr" class="text-xs">{{ \\Illuminate\\Support\\Str::limit((string)($row[$header] ?? ''), 50) }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($preview['headers']) }}" class="py-8 text-center">لا توجد صفوف بيانات.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="flex justify-end gap-3">
            <a href="{{ route('datasets.operational-import') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink">رجوع</a>
            <button class="btn-motion rounded-md bg-brand-600 px-5 py-2 text-sm font-medium text-white">إنشاء الجدول واستيراد البيانات</button>
        </div>
    </form>
</div>

@php
    $datasetOptions = $datasets->map(function ($d) {
        return [
            'id' => $d->id,
            'fields' => $d->fields
                ->filter(function ($f) {
                    return $f->is_identifier || $f->is_unique;
                })
                ->map(function ($f) {
                    return [
                        'id' => $f->id,
                        'name' => $f->name,
                        'display_name' => $f->display_name,
                        'data_type' => $f->data_type,
                    ];
                })
                ->values()
                ->all(),
        ];
    })->values()->all();
@endphp

<script>
document.addEventListener('DOMContentLoaded', () => {
    const datasets = @json($datasetOptions);
    const datasetSelect = document.getElementById('parent_dataset_id');
    const fieldSelect = document.getElementById('parent_field_id');
    const oldField = @json(old('parent_field_id'));

    function refreshFields() {
        const dataset = datasets.find(item => String(item.id) === String(datasetSelect.value));
        fieldSelect.innerHTML = '<option value="">اختر حقل البئر</option>';
        (dataset?.fields || []).forEach(field => {
            const option = document.createElement('option');
            option.value = field.id;
            option.textContent = field.display_name + ' (' + field.data_type + ')';
            if (String(field.id) === String(oldField)) option.selected = true;
            fieldSelect.appendChild(option);
        });
    }

    datasetSelect.addEventListener('change', refreshFields);
    refreshFields();
});
</script>
@endsection
