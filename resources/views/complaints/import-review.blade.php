@extends('layouts.app')

@section('title', 'مراجعة الشكاوى المشكوك بتكرارها')

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-ink">مراجعة الشكاوى المشكوك بتكرارها</h2>
        <p class="mt-1 text-sm text-ink-secondary">
            النظام لا يحذف أو يتجاوز أي شكوى تلقائياً. راجع الحالات المشكوك بتكرارها واختر القرار لكل صف.
        </p>
    </div>

    @if($errors->any())
        <div class="mb-5 rounded-md border border-danger bg-danger-surface p-4 text-sm text-danger">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="card-institutional p-4"><div class="text-xs text-ink-secondary">إجمالي الصفوف</div><div class="mt-1 text-2xl font-semibold text-ink">{{ $analysis['total_rows'] }}</div></div>
        <div class="card-institutional p-4"><div class="text-xs text-ink-secondary">شكاوى جديدة</div><div class="mt-1 text-2xl font-semibold text-success">{{ $analysis['new_rows'] }}</div></div>
        <div class="card-institutional p-4"><div class="text-xs text-ink-secondary">تحتاج قراراً</div><div class="mt-1 text-2xl font-semibold text-warning">{{ count($analysis['suspicious']) }}</div></div>
    </div>

    <form method="POST" action="{{ route('complaints.import.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        @if(count($analysis['suspicious']) === 0)
            <div class="card-institutional p-6">
                <div class="font-medium text-ink">لم يتم العثور على شكاوى مشابهة بشكل كافٍ.</div>
                <p class="mt-1 text-sm text-ink-secondary">يمكنك متابعة الاستيراد، وستتم إضافة الصفوف التي اجتازت الفحص.</p>
            </div>
        @else
            @foreach($analysis['suspicious'] as $item)
                <div class="card-institutional overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-4">
                        <div>
                            <div class="font-semibold text-ink">صف Excel رقم {{ $item['row'] }}</div>
                            <div class="mt-1 text-xs text-ink-secondary">درجة أعلى تطابق: {{ $item['score'] }}%</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="inline-flex items-center gap-2 rounded-md border border-success bg-white px-3 py-2 text-sm">
                                <input type="radio" name="decisions[{{ $item['row'] }}]" value="import" required>
                                استيراد كشكوى جديدة
                            </label>
                            <label class="inline-flex items-center gap-2 rounded-md border border-danger bg-white px-3 py-2 text-sm">
                                <input type="radio" name="decisions[{{ $item['row'] }}]" value="skip" required>
                                تجاوز
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-2">
                        <div class="rounded-md border border-border bg-surface-1 p-4">
                            <div class="mb-3 text-sm font-semibold text-ink">الشكوى من الملف</div>
                            <dl class="space-y-2 text-sm">
                                <div><dt class="inline text-ink-secondary">رقم الشكوى:</dt> <dd class="inline text-ink">{{ $item['data']['complaint_number'] ?: 'سيتم توليده' }}</dd></div>
                                <div><dt class="inline text-ink-secondary">العنوان:</dt> <dd class="inline text-ink">{{ $item['data']['title'] }}</dd></div>
                                <div><dt class="inline text-ink-secondary">الوصف:</dt> <dd class="inline text-ink">{{ $item['data']['description'] ?: '—' }}</dd></div>
                                <div><dt class="inline text-ink-secondary">المواطن:</dt> <dd class="inline text-ink">{{ $item['data']['contact_name'] ?: '—' }}</dd></div>
                                <div><dt class="inline text-ink-secondary">الهاتف:</dt> <dd class="inline text-ink">{{ $item['data']['contact_phone'] ?: '—' }}</dd></div>
                                <div><dt class="inline text-ink-secondary">الموقع:</dt> <dd class="inline text-ink">{{ $item['data']['address'] ?: '—' }}</dd></div>
                            </dl>
                        </div>

                        <div class="space-y-3">
                            @foreach($item['matches'] as $match)
                                <div class="rounded-md border border-warning bg-white p-4">
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <div class="font-semibold text-ink">{{ $match['complaint_number'] }}</div>
                                        <span class="rounded-full bg-warning-surface px-2 py-1 text-xs font-medium text-warning">{{ $match['score'] }}%</span>
                                    </div>
                                    <div class="text-sm font-medium text-ink">{{ $match['title'] }}</div>
                                    <div class="mt-2 text-xs text-ink-secondary">المواطن: {{ $match['contact_name'] ?: '—' }} · الهاتف: {{ $match['contact_phone'] ?: '—' }}</div>
                                    <div class="mt-1 text-xs text-ink-secondary">الموقع: {{ $match['address'] ?: '—' }}</div>
                                    <div class="mt-2 text-xs text-warning">سبب الاشتباه: {{ $match['reason'] }}</div>
                                    <a href="{{ route('complaints.show', $match['id']) }}" target="_blank" class="mt-3 inline-block text-sm font-medium text-brand-600 hover:underline">فتح الشكوى الموجودة</a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        @endif

        <div class="card-institutional flex flex-wrap items-center justify-between gap-3 p-4">
            <a href="{{ route('complaints.import.mapping', ['token' => $token]) }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">العودة للأعمدة</a>
            <button type="submit" class="rounded-md bg-brand-600 px-5 py-2 text-sm font-medium text-white hover:bg-brand-700">تنفيذ الاستيراد حسب قراراتي</button>
        </div>
    </form>

    @if(!empty($analysis['errors']))
        <div class="mt-5 card-institutional border-danger p-4">
            <div class="font-medium text-danger">صفوف لم يمكن فحصها</div>
            @foreach($analysis['errors'] as $error)
                <div class="mt-1 text-sm text-danger">الصف {{ $error['row'] }}: {{ $error['error'] }}</div>
            @endforeach
        </div>
    @endif
</div>
@endsection
