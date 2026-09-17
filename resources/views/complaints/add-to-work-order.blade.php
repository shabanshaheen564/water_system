@extends('layouts.app')

@section('title', 'إضافة الشكوى إلى مهمة')

@section('content')
<div class="mx-auto max-w-4xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-xl font-semibold text-ink">إضافة الشكوى إلى مهمة موجودة</h2>
            <code class="ltr-value text-sm text-brand-600">{{ $complaint->complaint_number }}</code>
        </div>
        <p class="mt-1 text-sm text-ink-secondary">اربط هذه الشكوى بمهمة تعالج نفس المشكلة بدل إنشاء مهمة جديدة.</p>
    </div>

    @if($errors->any())
        <div class="mb-5 rounded-md border border-danger bg-danger-surface p-4 text-sm text-danger">{{ $errors->first() }}</div>
    @endif

    <section class="card-institutional mb-6 p-6">
        <h3 class="mb-4 text-base font-semibold text-ink">الشكوى</h3>
        <dl class="grid gap-4 md:grid-cols-2">
            <div><dt class="text-xs text-ink-muted">العنوان</dt><dd class="mt-1 text-sm font-medium text-ink">{{ $complaint->title }}</dd></div>
            <div><dt class="text-xs text-ink-muted">الأولوية</dt><dd class="mt-1 text-sm text-ink">{{ ['low'=>'منخفضة','medium'=>'متوسطة','high'=>'عالية','urgent'=>'عاجلة'][$complaint->priority] ?? $complaint->priority }}</dd></div>
            <div class="md:col-span-2"><dt class="text-xs text-ink-muted">الوصف</dt><dd class="mt-1 whitespace-pre-wrap text-sm leading-7 text-ink-secondary">{{ $complaint->description }}</dd></div>
        </dl>
    </section>

    <form method="POST" action="{{ route('complaints.add-to-work-order.store', $complaint) }}" class="card-institutional p-6">
        @csrf
        <h3 class="mb-5 text-base font-semibold text-ink">اختر المهمة</h3>

        @if($workOrders->isEmpty())
            <div class="rounded-md border border-border bg-surface-1 p-4 text-sm text-ink-secondary">
                لا توجد مهام مفتوحة يمكن إضافة الشكوى إليها حاليًا.
            </div>
        @else
            <div class="space-y-3">
                @foreach($workOrders as $workOrder)
                    <label class="flex cursor-pointer items-start gap-3 rounded-md border border-border p-4 hover:bg-surface-1">
                        <input type="radio" name="work_order_id" value="{{ $workOrder->id }}" class="mt-1" required>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <code class="ltr-value text-sm text-brand-600">{{ $workOrder->work_order_number }}</code>
                                <span class="text-sm font-medium text-ink">{{ $workOrder->title }}</span>
                            </span>
                            <span class="mt-1 block text-xs text-ink-secondary">
                                المسؤول: {{ $workOrder->assignedTo->name ?? 'غير مسند' }}
                                · الحالة: {{ ['pending'=>'معلقة','assigned'=>'مسندة','in_progress'=>'قيد التنفيذ'][$workOrder->status] ?? $workOrder->status }}
                                · الأولوية: {{ ['low'=>'منخفضة','medium'=>'متوسطة','high'=>'عالية','urgent'=>'عاجلة'][$workOrder->priority] ?? $workOrder->priority }}
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-2">
                <a href="{{ route('complaints.show', $complaint) }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">إلغاء</a>
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">إضافة إلى المهمة</button>
            </div>
        @endif
    </form>
</div>
@endsection
