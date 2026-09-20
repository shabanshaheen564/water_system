@extends('layouts.app')
@section('title', 'استيراد طبقة GIS')
@section('content')
<div class="mx-auto max-w-4xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-ink">استيراد طبقة GIS</h2>
        <p class="mt-1 text-sm text-ink-secondary">ارفع ملفات Shapefile الأساسية مع ملف .prj إن وجد لاكتشاف نظام الإحداثيات.</p>
    </div>
    <div class="card-institutional p-6">
        <form method="POST" action="{{ route('datasets.import.preview') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">ملفات Shapefile</label>
                <input type="file" name="files[]" multiple required accept=".shp,.shx,.dbf,.prj,.cpg,.dbt" class="input-institutional w-full px-3 py-2">
                <p class="mt-2 text-xs text-ink-muted">يجب اختيار .shp و .shx و .dbf، ويفضل .prj و .cpg. حجم الملف الواحد حتى 50MB.</p>
            </div>
            <div class="rounded-md border border-brand-200 bg-brand-50 p-4 text-sm text-brand-900">
                <strong>مهم:</strong> بعد قراءة الملفات سيظهر لك Geometry وCRS والحقول المكتشفة، وبعدها تختار بنفسك Official أو Web Editable أو Operational أو Analytical.
            </div>
            @error('files')<p class="text-sm text-danger">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-3 border-t border-border pt-6">
                <a href="{{ route('datasets.index') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink">إلغاء</a>
                <button class="btn-motion rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white">فحص الطبقة</button>
            </div>
        </form>
    </div>
</div>
@endsection
