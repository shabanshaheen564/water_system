@extends('layouts.app')

@section('title', 'إضافة جدول تشغيلي')

@section('content')
<div class="mx-auto max-w-4xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-ink">إضافة جدول تشغيلي من Excel / CSV</h2>
        <p class="mt-1 text-sm text-ink-secondary">ارفع بيانات TDS أو جودة المياه أو التشغيل، ثم اربطها بالبئر من خلال حقل مشترك.</p>
    </div>

    <div class="card-institutional p-6">
        <form method="POST" action="{{ route('datasets.operational-import.preview') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">ملف البيانات</label>
                <input type="file" name="file" required accept=".xlsx,.csv" class="input-institutional w-full px-3 py-2">
                <p class="mt-2 text-xs text-ink-muted">حتى 50MB. الصف الأول يجب أن يحتوي أسماء الأعمدة.</p>
                @error('file')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
            </div>

            <div class="rounded-md border border-brand-200 bg-brand-50 p-4 text-sm text-brand-900">
                <strong>مثال:</strong> يمكن أن يحتوي الملف على <code dir="ltr">Well_id</code> و <code dir="ltr">TDS</code> و <code dir="ltr">PH</code> و <code dir="ltr">Sample_Date</code>. لن نعدل طبقة الآبار الأصلية؛ سيتم إنشاء جدول تشغيلي مستقل وربطه بها.
            </div>

            <div class="flex justify-end gap-3 border-t border-border pt-6">
                <a href="{{ route('datasets.index') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink">إلغاء</a>
                <button class="btn-motion rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white">فحص الملف</button>
            </div>
        </form>
    </div>
</div>
@endsection
