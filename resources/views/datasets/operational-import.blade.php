@extends('layouts.app')

@section('title', 'استيراد بيانات تشغيلية')

@section('content')
<div class="mx-auto max-w-5xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <a href="{{ route('datasets.show', $dataset) }}" class="text-sm text-brand-700 hover:underline">← العودة إلى الطبقة</a>
        <h2 class="mt-3 text-xl font-semibold text-ink">استيراد بيانات تشغيلية وربطها بالطبقة</h2>
        <p class="mt-1 text-sm text-ink-secondary">الطبقة: {{ $dataset->display_name }}. سيتم تحديث السجلات الموجودة فقط ولن يتم إنشاء معالم مكانية جديدة.</p>
    </div>

    @if($errors->any())
        <div class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-disc space-y-1 ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card-institutional p-6">
        <form method="POST" action="{{ route('datasets.operational-import.preview', $dataset) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            <div>
                <label class="mb-2 block text-sm font-medium text-ink">ملف البيانات التشغيلية</label>
                <input type="file" name="file" accept=".csv,.xlsx" required class="block w-full rounded-md border border-border-strong bg-white px-3 py-2 text-sm">
                <p class="mt-2 text-xs text-ink-muted">الصيغ المدعومة: CSV و XLSX — الحد الأقصى 50MB.</p>
            </div>
            <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <strong>مهم:</strong> المطابقة تتم على سجل موجود داخل الطبقة. لن يتم حذف Geometry ولن يتم إنشاء Feature جديد.
            </div>
            <div class="flex justify-end gap-3">
                <a href="{{ route('datasets.show', $dataset) }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm">إلغاء</a>
                <button type="submit" class="rounded-md bg-brand-600 px-5 py-2 text-sm font-medium text-white hover:bg-brand-700">قراءة الملف والمتابعة</button>
            </div>
        </form>
    </div>
</div>
@endsection
