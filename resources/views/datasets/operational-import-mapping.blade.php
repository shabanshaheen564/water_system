@extends('layouts.app')

@section('title', 'ربط البيانات التشغيلية')

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <a href="{{ route('datasets.show', $dataset) }}" class="text-sm text-brand-700 hover:underline">← العودة إلى الطبقة</a>
        <h2 class="mt-3 text-xl font-semibold text-ink">ربط أعمدة البيانات التشغيلية</h2>
        <p class="mt-1 text-sm text-ink-secondary">اختر عمود المطابقة من الملف والحقل المقابل داخل الطبقة، ثم حدد الحقول التشغيلية التي تريد تحديثها.</p>
    </div>

    @if($errors->any())
        <div class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-disc space-y-1 ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('datasets.operational-import.confirm', $dataset) }}" class="space-y-6">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <section class="card-institutional p-6">
            <h3 class="text-base font-semibold text-ink">1. مفتاح الربط</h3>
            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-ink">عمود المطابقة في الملف</label>
                    <select name="match_source_column" required class="w-full rounded-md border border-border-strong bg-white px-3 py-2 text-sm">
                        <option value="">اختر العمود</option>
                        @foreach($headers as $header)
                            <option value="{{ $header }}" @selected(old('match_source_column') === $header)>{{ $header }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-ink">الحقل المقابل في الطبقة</label>
                    <select name="match_target_field" required class="w-full rounded-md border border-border-strong bg-white px-3 py-2 text-sm">
                        <option value="">اختر الحقل</option>
                        @foreach($dataset->fields->sortBy('sort_order') as $field)
                            <option value="{{ $field->name }}" @selected(old('match_target_field') === $field->name)>{{ $field->display_name }} ({{ $field->name }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="mt-3 text-xs text-ink-muted">مثال: Asset_ID في ملف التشغيل ↔ Asset_ID في طبقة الآبار أو الشبكة.</p>
        </section>

        <section class="card-institutional overflow-hidden">
            <div class="border-b border-border px-6 py-4">
                <h3 class="text-base font-semibold text-ink">2. ربط الحقول التشغيلية</h3>
                <p class="mt-1 text-xs text-ink-muted">يمكنك اختيار "لا تستورد" للأعمدة التي لا تحتاجها.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="table-institutional">
                    <thead><tr><th>عمود الملف</th><th>الحقل داخل الطبقة</th></tr></thead>
                    <tbody>
                    @foreach($headers as $header)
                        <tr>
                            <td><code class="ltr-value text-sm">{{ $header }}</code></td>
                            <td>
                                <select name="column_mapping[{{ $header }}]" class="w-full min-w-[280px] rounded-md border border-border-strong bg-white px-3 py-2 text-sm">
                                    <option value="">لا تستورد</option>
                                    @foreach($dataset->fields->sortBy('sort_order') as $field)
                                        <option value="{{ $field->name }}" @selected(old("column_mapping.{$header}") === $field->name || old('column_mapping.'.$header) === null && $field->name === $header)>{{ $field->display_name }} ({{ $field->name }})</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
            <strong>حماية البيانات:</strong> العملية تعدّل قيم الحقول المرتبطة بالسجلات الموجودة فقط. Geometry ومعرّف السجل لا يتم استبدالهما.
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('datasets.show', $dataset) }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm">إلغاء</a>
            <button type="submit" class="rounded-md bg-brand-600 px-5 py-2 text-sm font-medium text-white hover:bg-brand-700">تنفيذ الربط والاستيراد</button>
        </div>
    </form>
</div>
@endsection
