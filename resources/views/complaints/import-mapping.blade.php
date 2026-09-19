@extends('layouts.app')

@section('title', 'تحديد أعمدة استيراد الشكاوى')

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-ink">تحديد أعمدة الاستيراد</h2>
        <p class="mt-1 text-sm text-ink-secondary">راجع الأعمدة التي تم التعرف عليها تلقائياً، وحدد الحقل المقابل لكل عمود قبل بدء الاستيراد.</p>
    </div>

    @if($errors->any())
        <div class="mb-5 rounded-md border border-danger bg-danger-surface p-4 text-sm text-danger">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="card-institutional mb-5 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-sm font-medium text-ink">معاينة الملف</div>
                <div class="mt-1 text-xs text-ink-secondary">عدد السجلات: {{ $totalRows }}</div>
            </div>
            <a href="{{ route('complaints.import') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">اختيار ملف آخر</a>
        </div>
    </div>

    <form method="POST" action="{{ route('complaints.import.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="card-institutional overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-institutional">
                    <thead>
                        <tr>
                            <th>عمود الملف</th>
                            <th>الحقل في النظام</th>
                            <th>مثال من الملف</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($headers as $index => $header)
                        <tr>
                            <td class="whitespace-nowrap font-medium text-ink">{{ $header !== '' ? $header : 'عمود بدون اسم' }}</td>
                            <td class="min-w-64">
                                <select name="mapping[{{ $index }}]" class="input-institutional w-full text-sm">
                                    <option value="">تجاهل هذا العمود</option>
                                    @foreach($targetFields as $field => $label)
                                        <option value="{{ $field }}" @selected(($autoMapping[$index] ?? '') === $field)>{{ $label }} ({{ $field }})</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="max-w-md text-sm text-ink-secondary">
                                {{ $sampleRows[0][$index] ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-institutional flex flex-wrap items-center justify-between gap-3 p-4">
            <p class="text-xs text-ink-secondary">حقل <strong class="text-ink">العنوان</strong> مطلوب. الأعمدة التي تختار تجاهلها لن يتم استيرادها.</p>
            <div class="flex items-center gap-2">
                <a href="{{ route('complaints.import') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">إلغاء</a>
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">تأكيد الاستيراد</button>
            </div>
        </div>
    </form>
</div>
@endsection
