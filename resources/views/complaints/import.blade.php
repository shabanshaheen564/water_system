@extends('layouts.app')

@section('title', 'استيراد الشكاوى')

@section('content')
<div class="mx-auto max-w-5xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-ink">استيراد الشكاوى</h2>
        <p class="mt-1 text-sm text-ink-secondary">يدعم Excel بصيغ XLSX وXLS وملفات CSV. يتم التعرف تلقائياً على أسماء الأعمدة العربية والإنجليزية الشائعة.</p>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-md border border-success bg-success-surface p-4 text-sm text-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-md border border-danger bg-danger-surface p-4 text-sm text-danger">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="card-institutional p-6">
        <form method="POST" action="{{ route('complaints.import.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf
            <div>
                <label class="mb-2 block text-sm font-medium text-ink">ملف الشكاوى</label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv,.txt" required class="input-institutional w-full">
                <p class="mt-2 text-xs text-ink-muted">الحد الأقصى 10 MB. الصف الأول يجب أن يحتوي على أسماء الأعمدة.</p>
            </div>
            <div class="rounded-md border border-border bg-surface-1 p-4 text-sm text-ink-secondary">
                <strong class="text-ink">الأعمدة التي يمكن التعرف عليها:</strong>
                complaint_number, title, description, status, priority, contact_name, contact_phone, address, latitude, longitude, assigned_to, processing_notes, solution.
                <br>العنوان/title مطلوب، وإذا لم يوجد رقم شكوى يتم توليده تلقائياً.
            </div>
            <div class="flex gap-2">
                <button class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">بدء الاستيراد</button>
                <a href="{{ route('complaints.index') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink">إلغاء</a>
            </div>
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
@endsection
