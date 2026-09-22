@extends('layouts.app')

@section('title', 'استيراد الشكاوى')

@section('content')
<div class="mx-auto max-w-5xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-ink">استيراد الشكاوى</h2>
        <p class="mt-1 text-sm text-ink-secondary">اختر ملف Excel أو CSV، ثم سيتم نقلك مباشرة إلى صفحة تحديد الأعمدة قبل تنفيذ الاستيراد.</p>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-md border border-success bg-success-surface p-4 text-sm text-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-md border border-danger bg-danger-surface p-4 text-sm text-danger">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="card-institutional p-8">
        <form id="complaint-import-form" method="POST" action="{{ route('complaints.import.preview') }}" enctype="multipart/form-data">
            @csrf
            <input id="complaint-import-file" type="file" name="file" accept=".xlsx,.xls,.csv,.txt" required class="sr-only">

            <label for="complaint-import-file" class="mx-auto flex min-h-56 max-w-2xl cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-border-strong bg-surface-1 px-6 text-center transition hover:bg-white">
                <div class="text-sm font-semibold text-ink">ملف الشكاوى</div>
                <div class="mt-2 text-xs text-ink-secondary">Excel: XLSX / XLS أو CSV</div>
                <span class="mt-5 inline-flex rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">استيراد ملف</span>
                <div id="selected-file-name" class="mt-3 text-xs text-ink-muted">لم يتم اختيار ملف</div>
            </label>

            <p class="mt-3 text-center text-xs text-ink-muted">الحد الأقصى 10 MB. بعد اختيار الملف ستنتقل تلقائياً إلى تحديد الأعمدة.</p>

            <noscript>
                <div class="mt-4 text-center">
                    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white">متابعة</button>
                </div>
            </noscript>
        </form>
    </div>

    @if(session('import_result'))
        @php($result = session('import_result'))
        <div class="card-institutional mt-6 p-6">
            <h3 class="font-semibold text-ink">نتيجة الاستيراد</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-4">
                <div class="rounded-md border border-border p-4"><div class="text-xs text-ink-muted">الإجمالي</div><div class="mt-1 text-xl font-semibold">{{ $result['total_rows'] }}</div></div>
                <div class="rounded-md border border-success p-4"><div class="text-xs text-ink-muted">تمت الإضافة</div><div class="mt-1 text-xl font-semibold">{{ $result['created'] }}</div></div>
                <div class="rounded-md border border-warning p-4"><div class="text-xs text-ink-muted">تم تجاوزها</div><div class="mt-1 text-xl font-semibold">{{ $result['skipped'] }}</div></div>
                <div class="rounded-md border border-danger p-4"><div class="text-xs text-ink-muted">فشل</div><div class="mt-1 text-xl font-semibold">{{ $result['failed'] }}</div></div>
            </div>
            @if(!empty($result['errors']))
                <div class="mt-5 overflow-x-auto">
                    <table class="table-institutional"><thead><tr><th>الصف</th><th>الخطأ</th></tr></thead><tbody>
                    @foreach($result['errors'] as $error)<tr><td>{{ $error['row'] ?? '—' }}</td><td>{{ $error['error'] ?? 'خطأ غير معروف' }}</td></tr>@endforeach
                    </tbody></table>
                </div>
            @endif
        </div>
    @endif
</div>

<script>
document.getElementById('complaint-import-file')?.addEventListener('change', function () {
    if (!this.files?.length) return;
    document.getElementById('selected-file-name').textContent = this.files[0].name;
    document.getElementById('complaint-import-form').submit();
});
</script>
@endsection
