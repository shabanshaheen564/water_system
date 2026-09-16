@extends('layouts.app')

@section('title', 'الشكاوى')

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold text-ink">الشكاوى</h2>
            <p class="mt-1 text-sm text-ink-secondary">تسجيل ومتابعة شكاوى المواطنين المتعلقة بخدمات المياه.</p>
        </div>
        @can('complaints.create')
            <a href="{{ route('complaints.create') }}" class="btn-motion rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">تسجيل شكوى</a>
        @endcan
    </div>

    @if($errors->has('delete'))
        <div class="mb-5 rounded-md border border-danger bg-danger-surface p-4 text-sm text-danger">{{ $errors->first('delete') }}</div>
    @endif

    <form method="GET" class="card-institutional mb-5 grid gap-4 p-4 md:grid-cols-4">
        <div class="md:col-span-2">
            <label for="search" class="mb-1 block text-sm font-medium text-ink">بحث</label>
            <input id="search" name="search" value="{{ $search }}" class="input-institutional w-full text-sm" placeholder="رقم الشكوى، العنوان، اسم المواطن أو الهاتف">
        </div>
        <div>
            <label for="status" class="mb-1 block text-sm font-medium text-ink">الحالة</label>
            <select id="status" name="status" class="input-institutional w-full text-sm">
                <option value="">كل الحالات</option><option value="open" @selected($status === 'open')>جديدة</option><option value="in_progress" @selected($status === 'in_progress')>قيد المعالجة</option><option value="resolved" @selected($status === 'resolved')>تم الحل</option><option value="closed" @selected($status === 'closed')>مغلقة</option><option value="cancelled" @selected($status === 'cancelled')>ملغاة</option>
            </select>
        </div>
        <div>
            <label for="priority" class="mb-1 block text-sm font-medium text-ink">الأولوية</label>
            <select id="priority" name="priority" class="input-institutional w-full text-sm">
                <option value="">كل الأولويات</option><option value="low" @selected($priority === 'low')>منخفضة</option><option value="medium" @selected($priority === 'medium')>متوسطة</option><option value="high" @selected($priority === 'high')>عالية</option><option value="urgent" @selected($priority === 'urgent')>عاجلة</option>
            </select>
        </div>
        <div class="md:col-span-4 flex gap-2 border-t border-border pt-4"><button class="rounded-md bg-ink px-4 py-2 text-sm font-medium text-white">تطبيق البحث</button><a href="{{ route('complaints.index') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">مسح</a></div>
    </form>

    <div data-enter class="card-institutional overflow-hidden">
        <div class="overflow-x-auto"><table class="table-institutional"><thead><tr><th>رقم الشكوى</th><th>العنوان</th><th>المواطن</th><th>الأولوية</th><th>الحالة</th><th>المسند إليه</th><th>التاريخ</th><th>الإجراءات</th></tr></thead><tbody>
        @forelse($complaints as $complaint)
            <tr class="hover:bg-surface-1">
                <td class="whitespace-nowrap"><code class="ltr-value text-sm font-medium text-ink">{{ $complaint->complaint_number }}</code></td>
                <td><div class="max-w-xs truncate text-sm font-medium text-ink">{{ $complaint->title }}</div></td>
                <td class="whitespace-nowrap text-sm text-ink-secondary">{{ $complaint->contact_name ?? '—' }}</td>
                <td class="whitespace-nowrap">@php($priorityClasses = ['low'=>'border-border bg-surface-1 text-ink-secondary','medium'=>'border-info bg-info-surface text-info','high'=>'border-warning bg-warning-surface text-warning','urgent'=>'border-danger bg-danger-surface text-danger']) @php($priorityLabels = ['low'=>'منخفضة','medium'=>'متوسطة','high'=>'عالية','urgent'=>'عاجلة'])<span class="inline-flex rounded-md border px-2 py-1 text-xs font-medium {{ $priorityClasses[$complaint->priority] ?? $priorityClasses['medium'] }}">{{ $priorityLabels[$complaint->priority] ?? $complaint->priority }}</span></td>
                <td class="whitespace-nowrap">@php($statusLabels = ['open'=>'جديدة','in_progress'=>'قيد المعالجة','resolved'=>'تم الحل','closed'=>'مغلقة','cancelled'=>'ملغاة'])<span class="inline-flex rounded-md border border-border bg-surface-1 px-2 py-1 text-xs font-medium text-ink-secondary">{{ $statusLabels[$complaint->status] ?? $complaint->status }}</span></td>
                <td class="whitespace-nowrap text-sm text-ink-secondary">{{ $complaint->assignedTo->name ?? 'غير مسندة' }}</td>
                <td class="whitespace-nowrap text-sm text-ink-secondary ltr-value">{{ $complaint->created_at?->format('Y-m-d H:i') }}</td>
                <td class="whitespace-nowrap"><div class="flex items-center gap-3 text-sm font-medium"><a href="{{ route('complaints.show', $complaint) }}" class="text-brand-600 hover:text-brand-700">عرض</a>@can('complaints.update')<a href="{{ route('complaints.edit', $complaint) }}" class="text-ink-secondary hover:text-ink">تعديل</a>@endcan @can('complaints.delete') @if(($complaint->work_orders_count ?? 0) === 0)<form method="POST" action="{{ route('complaints.destroy', $complaint) }}" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف الشكوى؟');">@csrf @method('DELETE')<button class="text-danger hover:underline">حذف</button></form>@endif @endcan</div></td>
            </tr>
        @empty
            <tr><td colspan="8" class="py-12 text-center text-sm text-ink-muted">لا توجد شكاوى مسجلة.</td></tr>
        @endforelse
        </tbody></table></div>{{ $complaints->links() }}
    </div>
</div>
@endsection
