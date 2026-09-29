@php($updateMode = $updateMode ?? false)
@extends('layouts.app')

@section('title', $updateMode ? 'تحديث البيانات التشغيلية' : 'ربط البيانات التشغيلية')

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <a href="{{ route('datasets.show', $dataset) }}" class="text-sm text-brand-700 hover:underline">← العودة إلى {{ $updateMode ? 'الجدول التشغيلي' : 'الطبقة' }}</a>
        <h2 class="mt-3 text-xl font-semibold text-ink">{{ $updateMode ? 'مطابقة ملف التحديث' : 'ربط البيانات التشغيلية بجدول داعم' }}</h2>
        <p class="mt-1 text-sm text-ink-secondary">
            @if($updateMode)
                الجدول الحالي: <span dir="ltr" class="font-mono">{{ $dataset->name }}</span> — الطبقة الأساسية: {{ $parentDataset->display_name }}.
            @else
                سيتم إنشاء جدول تشغيلي مستقل لهذا الملف مرتبط بالطبقة الأساسية بعلاقة 1 إلى متعدد.
            @endif
        </p>
    </div>
    @if($errors->any())
        <div class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc space-y-1 ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ $updateMode ? route('datasets.operational-import.update.confirm', $dataset) : route('datasets.operational-import.confirm', $dataset) }}" class="space-y-6">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <section class="card-institutional p-6">
            <h3 class="text-base font-semibold text-ink">1. {{ $updateMode ? 'مفتاح التحديث' : 'مفتاح الربط' }}</h3>
            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-ink">{{ $updateMode ? 'عمود المفتاح في الملف الجديد' : 'عمود البئر/المعلم في الملف' }}</label>
                    <select name="match_source_column" required class="w-full rounded-md border border-border-strong bg-white px-3 py-2 text-sm">
                        <option value="">اختر العمود</option>
                        @foreach($headers as $header)<option value="{{ $header }}" @selected(old('match_source_column') === $header)>{{ $header }}</option>@endforeach
                    </select>
                </div>
                <div class="rounded-md border border-brand-200 bg-brand-50 px-4 py-3 text-sm">
                    <div class="text-xs text-ink-secondary">{{ $updateMode ? 'الحقل المقابل في الجدول التشغيلي' : 'سيتم الربط تلقائياً مع Identifier في الطبقة الأساسية' }}</div>
                    <div class="mt-1 font-semibold text-ink">{{ $updateMode && isset($childField) ? $childField->display_name.' ('.$childField->name.')' : $identifierField->display_name.' ('.$identifierField->name.')' }}</div>
                </div>
            </div>
            <p class="mt-3 text-xs text-ink-muted">
@if($updateMode)
    اختر العمود الذي يحتوي على نفس قيم المفتاح الموجودة في الجدول الحالي. سيتم تحديث السجلات المطابقة وإضافة المفاتيح الجديدة.
@else
    مثال: Well ID في الملف ↔ WELL_ID في طبقة Wells. هذا الحقل هو مفتاح الربط فقط ولا يتم إنشاء Geometry جديدة.
@endif
</p>
        </section>
        <section class="card-institutional overflow-hidden">
            <div class="border-b border-border px-6 py-4">
                <h3 class="text-base font-semibold text-ink">2. {{ $updateMode ? 'الأعمدة التي سيتم تحديثها' : 'أعمدة الجدول الداعم' }}</h3>
                <p class="mt-1 text-xs text-ink-muted">{{ $updateMode ? 'عمود المفتاح محفوظ تلقائياً. حدد الأعمدة التي تريد تحديثها.' : 'فعّل فقط الأعمدة التي تريد حفظها. عمود الربط سيُحفظ تلقائياً.' }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="table-institutional">
                    <thead><tr><th class="w-24">{{ $updateMode ? 'تحديث' : 'استيراد' }}</th><th>عمود الملف</th><th>{{ $updateMode ? 'المعالجة' : 'مكان التخزين' }}</th></tr></thead>
                    <tbody>
                    @foreach($headers as $header)
                        <tr>
                            <td><input type="checkbox" name="import_columns[{{ $header }}]" value="1" @checked($header === old('match_source_column') || old("import_columns.{$header}")) class="h-4 w-4 rounded border-border-strong text-brand-600"></td>
                            <td><code class="ltr-value text-sm">{{ $header }}</code></td>
                            <td class="text-sm text-ink-secondary">@if($header === old('match_source_column'))<span class="font-medium text-brand-700">حقل المفتاح</span>@elseif($updateMode)<span>سيتم تحديث/إضافة هذا الحقل</span>@else<span>حقل جديد داخل الجدول الداعم</span>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <div class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
@if($updateMode)
<strong>طريقة التحديث:</strong> السجل المطابق لمفتاح الربط سيتم تحديثه، والمفتاح الجديد سيتم إضافته. السجلات غير الموجودة في الملف تبقى كما هي، ولا يتم تعديل الطبقة الأساسية أو Geometry.
@else
<strong>حماية البيانات:</strong> سيتم إنشاء جدول داعم مستقل غير مكاني للبيانات التشغيلية، وإنشاء علاقة <strong>1 : N</strong> مع الطبقة الأساسية. لن يتم تعديل حقول الطبقة الأساسية، ولن يتم تغيير Geometry أو Feature ID.
@endif
</div>
        <div class="flex justify-end gap-3">
            <a href="{{ route('datasets.show', $dataset) }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm">إلغاء</a>
            <button type="submit" class="rounded-md bg-brand-600 px-5 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ $updateMode ? 'تحديث البيانات' : 'إنشاء الجدول الداعم واستيراد البيانات' }}</button>
        </div>
    </form>
</div>
@endsection
