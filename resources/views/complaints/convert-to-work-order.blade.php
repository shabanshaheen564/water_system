@extends('layouts.app')

@section('title', 'تحويل الشكوى إلى مهمة')

@section('content')
<div class="mx-auto max-w-4xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-ink">تحويل الشكوى إلى مهمة</h2>
                <code class="ltr-value text-sm text-brand-600">{{ $complaint->complaint_number }}</code>
            </div>
            <p class="mt-1 text-sm text-ink-secondary">{{ $complaint->title }}</p>
        </div>
        <a href="{{ route('complaints.show', $complaint) }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">العودة للشكوى</a>
    </div>

    @if($errors->any())
        <div class="mb-5 rounded-md border border-danger bg-danger-surface p-4 text-sm text-danger">
            <ul class="list-disc space-y-1 pr-5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <section class="card-institutional p-6 sm:p-8">
        <div class="mb-6 rounded-md border border-border bg-surface-1 p-4 text-sm text-ink-secondary">
            سيتم إنشاء مهمة مرتبطة بهذه الشكوى. يمكنك إسناد المهمة إلى نفسك أو إلى أي مستخدم نشط، مثل العامل الميداني أو المهندس أو الموظف.
        </div>

        <form method="POST" action="{{ route('complaints.work-order.store', $complaint) }}" class="space-y-6">
            @csrf
            <div class="grid gap-6 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="title" class="mb-2 block text-sm font-medium text-ink">عنوان المهمة</label>
                    <input id="title" name="title" value="{{ old('title', $complaint->title) }}" required maxlength="255" class="w-full rounded-md border border-border-strong bg-white px-3 py-2.5 text-sm text-ink focus:border-brand-600 focus:outline-none focus:ring-1 focus:ring-brand-600">
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="mb-2 block text-sm font-medium text-ink">وصف المهمة والتعليمات</label>
                    <textarea id="description" name="description" rows="5" required class="w-full rounded-md border border-border-strong bg-white px-3 py-2.5 text-sm leading-7 text-ink focus:border-brand-600 focus:outline-none focus:ring-1 focus:ring-brand-600">{{ old('description', $complaint->description) }}</textarea>
                </div>

                <div>
                    <label for="priority" class="mb-2 block text-sm font-medium text-ink">الأولوية</label>
                    <select id="priority" name="priority" required class="w-full rounded-md border border-border-strong bg-white px-3 py-2.5 text-sm text-ink focus:border-brand-600 focus:outline-none focus:ring-1 focus:ring-brand-600">
                        @foreach(['low'=>'منخفضة','medium'=>'متوسطة','high'=>'عالية','urgent'=>'عاجلة'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', $complaint->priority) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="assigned_to" class="mb-2 block text-sm font-medium text-ink">المسؤول عن المهمة</label>
                    <select id="assigned_to" name="assigned_to" required class="w-full rounded-md border border-border-strong bg-white px-3 py-2.5 text-sm text-ink focus:border-brand-600 focus:outline-none focus:ring-1 focus:ring-brand-600">
                        <option value="">اختر المسؤول</option>
                        @foreach($assignees as $assignee)
                            <option value="{{ $assignee->id }}" @selected((string) old('assigned_to', auth()->id()) === (string) $assignee->id)>{{ $assignee->name }} — {{ $assignee->email }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="notes" class="mb-2 block text-sm font-medium text-ink">ملاحظات إضافية</label>
                    <textarea id="notes" name="notes" rows="4" class="w-full rounded-md border border-border-strong bg-white px-3 py-2.5 text-sm leading-7 text-ink focus:border-brand-600 focus:outline-none focus:ring-1 focus:ring-brand-600">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-3 border-t border-border pt-6">
                <a href="{{ route('complaints.show', $complaint) }}" class="rounded-md border border-border-strong bg-white px-5 py-2.5 text-sm font-medium text-ink hover:bg-surface-1">إلغاء</a>
                <button type="submit" class="rounded-md bg-brand-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-700">تحويل إلى مهمة</button>
            </div>
        </form>
    </section>
</div>
@endsection
