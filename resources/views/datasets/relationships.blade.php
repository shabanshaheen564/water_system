@extends('layouts.app')

@section('title', 'علاقات البيانات')

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <a href="{{ route('datasets.show', $dataset) }}" class="text-sm text-brand-600">← العودة إلى {{ $dataset->display_name }}</a>
        <h2 class="mt-3 text-xl font-semibold text-ink">علاقات البيانات — {{ $dataset->display_name }}</h2>
        <p class="mt-1 text-sm text-ink-secondary">اربط البيانات التشغيلية مثل TDS وجودة المياه والتشغيل بحقل البئر المشترك.</p>
    </div>

    @if($errors->any())
        <div class="mb-6 rounded-md border border-danger/30 bg-danger/5 p-4 text-sm text-danger">
            <ul class="list-disc space-y-1 pe-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="card-institutional p-6">
        <h3 class="mb-4 text-base font-semibold text-ink">إضافة علاقة جديدة</h3>
        <form method="POST" action="{{ route('datasets.relationships.store', $dataset) }}" class="space-y-5">
            @csrf
            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">حقل البئر</label>
                    <select name="parent_field_id" required class="input-institutional w-full px-3 py-2">
                        <option value="">اختر Identifier / Unique</option>
                        @foreach($dataset->fields->filter(fn($f) => $f->is_identifier || $f->is_unique) as $field)
                            <option value="{{ $field->id }}" {{ old('parent_field_id') == $field->id ? 'selected' : '' }}>
                                {{ $field->display_name }} — {{ $field->data_type }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">الجدول التابع</label>
                    <select name="child_dataset_id" id="child_dataset_id" required class="input-institutional w-full px-3 py-2">
                        <option value="">اختر الجدول</option>
                        @foreach($datasets as $candidate)
                            <option value="{{ $candidate->id }}" {{ old('child_dataset_id') == $candidate->id ? 'selected' : '' }}>{{ $candidate->display_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">حقل الربط في الجدول التابع</label>
                    <select name="child_field_id" id="child_field_id" required class="input-institutional w-full px-3 py-2">
                        <option value="">اختر الجدول أولاً</option>
                    </select>
                </div>
            </div>

            <div class="rounded-md border border-brand-200 bg-brand-50 p-4 text-sm text-brand-900">
                الربط من نوع <strong>One-to-Many</strong>: بئر واحد يمكن أن يحتوي على عدة سجلات تشغيلية أو تحاليل عبر الزمن.
            </div>

            <button class="btn-motion rounded-md bg-brand-600 px-5 py-2 text-sm font-medium text-white">إنشاء العلاقة</button>
        </form>
    </section>

    <section class="card-institutional mt-6 overflow-hidden">
        <div class="border-b border-border px-6 py-4">
            <h3 class="text-base font-semibold text-ink">العلاقات الحالية</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="table-institutional">
                <thead><tr><th>الجدول التابع</th><th>حقل البئر</th><th>حقل الجدول</th><th>النوع</th><th>إجراء</th></tr></thead>
                <tbody>
                @forelse($relationships as $relationship)
                    <tr>
                        <td>{{ $relationship->childDataset->display_name }}</td>
                        <td><code dir="ltr">{{ $relationship->parentField->name }}</code></td>
                        <td><code dir="ltr">{{ $relationship->childField->name }}</code></td>
                        <td>One-to-Many</td>
                        <td>
                            <form method="POST" action="{{ route('datasets.relationships.destroy', [$dataset, $relationship]) }}" onsubmit="return confirm('حذف العلاقة فقط؟ لن يتم حذف البيانات.');">
                                @csrf @method('DELETE')
                                <button class="text-sm font-medium text-danger">حذف العلاقة</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-sm text-ink-muted">لا توجد علاقات حتى الآن.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const datasets = @json($datasets->map(fn($d) => [
        'id' => $d->id,
        'fields' => $d->fields->map(fn($f) => [
            'id' => $f->id,
            'display_name' => $f->display_name,
            'name' => $f->name,
            'data_type' => $f->data_type,
        ])->values(),
    ])->values());

    const datasetSelect = document.getElementById('child_dataset_id');
    const fieldSelect = document.getElementById('child_field_id');
    const oldField = @json(old('child_field_id'));

    function refreshFields() {
        const dataset = datasets.find(item => String(item.id) === String(datasetSelect.value));
        fieldSelect.innerHTML = '<option value="">اختر حقل الجدول</option>';
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
