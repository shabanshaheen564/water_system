@extends('layouts.app')

@section('title', 'تعديل الشكوى')

@section('content')
<div class="mx-auto max-w-5xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6"><h2 class="text-xl font-semibold text-ink">تعديل الشكوى <code class="ltr-value text-sm text-brand-600">{{ $complaint->complaint_number }}</code></h2><p class="mt-1 text-sm text-ink-secondary">تحديث بيانات الشكوى وحالتها وإسنادها.</p></div>

    <form method="POST" action="{{ route('complaints.update', $complaint) }}" data-enter class="card-institutional space-y-6 p-6">
        @csrf @method('PUT')
        <div class="grid gap-6 md:grid-cols-2">
            <div><label for="title" class="mb-1 block text-sm font-medium text-ink">عنوان المشكلة</label><input id="title" name="title" value="{{ old('title', $complaint->title) }}" required class="input-institutional w-full text-sm">@error('title')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror</div>
            <div><label for="priority" class="mb-1 block text-sm font-medium text-ink">الأولوية</label><select id="priority" name="priority" class="input-institutional w-full text-sm"><option value="low" @selected(old('priority', $complaint->priority)==='low')>منخفضة</option><option value="medium" @selected(old('priority', $complaint->priority)==='medium')>متوسطة</option><option value="high" @selected(old('priority', $complaint->priority)==='high')>عالية</option><option value="urgent" @selected(old('priority', $complaint->priority)==='urgent')>عاجلة</option></select></div>
        </div>
        <div><label for="description" class="mb-1 block text-sm font-medium text-ink">وصف المشكلة</label><textarea id="description" name="description" rows="4" required class="input-institutional w-full text-sm">{{ old('description', $complaint->description) }}</textarea>@error('description')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror</div>

        <div class="border-t border-border pt-6">
            <h3 class="mb-4 text-base font-semibold text-ink">الحالة والإسناد</h3>
            <div class="grid gap-6 md:grid-cols-2">
                <div><label for="status" class="mb-1 block text-sm font-medium text-ink">الحالة</label><select id="status" name="status" class="input-institutional w-full text-sm"><option value="open" @selected(old('status', $complaint->status)==='open')>جديدة</option><option value="in_progress" @selected(old('status', $complaint->status)==='in_progress')>قيد المعالجة</option><option value="resolved" @selected(old('status', $complaint->status)==='resolved')>تم الحل</option><option value="closed" @selected(old('status', $complaint->status)==='closed')>مغلقة</option><option value="cancelled" @selected(old('status', $complaint->status)==='cancelled')>ملغاة</option></select>@error('status')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror</div>
                <div><label for="assigned_to" class="mb-1 block text-sm font-medium text-ink">المسند إليه</label><select id="assigned_to" name="assigned_to" class="input-institutional w-full text-sm"><option value="">بدون إسناد</option>@foreach($assignees as $assignee)<option value="{{ $assignee->id }}" @selected((string) old('assigned_to', $complaint->assigned_to)===(string) $assignee->id)>{{ $assignee->name }} — {{ $assignee->email }}</option>@endforeach</select>@error('assigned_to')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror</div>
            </div>
        </div>

        <div class="border-t border-border pt-6"><h3 class="mb-4 text-base font-semibold text-ink">بيانات المواطن والموقع</h3><div class="grid gap-6 md:grid-cols-2"><div><label for="contact_name" class="mb-1 block text-sm font-medium text-ink">اسم المواطن</label><input id="contact_name" name="contact_name" value="{{ old('contact_name', $complaint->contact_name) }}" class="input-institutional w-full text-sm"></div><div><label for="contact_phone" class="mb-1 block text-sm font-medium text-ink">رقم الهاتف</label><input id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $complaint->contact_phone) }}" class="input-institutional w-full text-sm" dir="ltr"></div><div class="md:col-span-2"><label for="address" class="mb-1 block text-sm font-medium text-ink">العنوان / وصف الموقع</label><textarea id="address" name="address" rows="2" class="input-institutional w-full text-sm">{{ old('address', $complaint->address) }}</textarea></div><div><label for="latitude" class="mb-1 block text-sm font-medium text-ink">Latitude</label><input id="latitude" name="latitude" type="number" step="any" min="-90" max="90" value="{{ old('latitude', $complaint->latitude) }}" class="input-institutional w-full text-sm" dir="ltr"></div><div><label for="longitude" class="mb-1 block text-sm font-medium text-ink">Longitude</label><input id="longitude" name="longitude" type="number" step="any" min="-180" max="180" value="{{ old('longitude', $complaint->longitude) }}" class="input-institutional w-full text-sm" dir="ltr"></div></div></div>

        <div class="flex justify-end gap-3 border-t border-border pt-5"><a href="{{ route('complaints.show', $complaint) }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">إلغاء</a><button class="btn-motion rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">حفظ التعديلات</button></div>
    </form>
</div>
@endsection
