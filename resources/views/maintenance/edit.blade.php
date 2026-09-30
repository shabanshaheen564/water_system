@extends('layouts.app')

@section('title', 'تعديل طلب الصيانة')

@section('content')
<div class="mx-auto max-w-4xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6"><h2 class="text-xl font-semibold text-ink">تعديل طلب {{ $maintenanceRequest->request_no }}</h2></div>
    @if($errors->any())<div class="mb-5 rounded-md border border-danger bg-danger-surface p-4 text-sm text-danger"><ul class="list-disc pr-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('maintenance.update', $maintenanceRequest) }}" class="card-institutional grid gap-5 p-6">
        @csrf @method('PUT')
        <div class="grid gap-5 md:grid-cols-2"><div><label class="mb-1 block text-sm font-medium text-ink">الأولوية</label><select name="priority" class="input-institutional w-full">@foreach(['low'=>'منخفضة','medium'=>'متوسطة','high'=>'عالية','urgent'=>'عاجلة'] as $key=>$label)<option value="{{ $key }}" @selected($maintenanceRequest->priority===$key)>{{ $label }}</option>@endforeach</select></div><div><label class="mb-1 block text-sm font-medium text-ink">الفني المسند إليه</label><select name="assigned_to" class="input-institutional w-full"><option value="">بدون إسناد</option>@foreach($technicians as $technician)<option value="{{ $technician->id }}" @selected($maintenanceRequest->assigned_to===$technician->id)>{{ $technician->name }}</option>@endforeach</select></div></div>
        <div><label class="mb-1 block text-sm font-medium text-ink">وصف المشكلة</label><textarea name="problem_description" rows="4" class="input-institutional w-full" required>{{ $maintenanceRequest->problem_description }}</textarea></div>
        <div><label class="mb-1 block text-sm font-medium text-ink">وصف العطل</label><textarea name="fault_description" rows="3" class="input-institutional w-full">{{ $maintenanceRequest->fault_description }}</textarea></div>
        <div><label class="mb-1 block text-sm font-medium text-ink">ملاحظات</label><textarea name="notes" rows="3" class="input-institutional w-full">{{ $maintenanceRequest->notes }}</textarea></div>
        <div class="flex gap-2 border-t border-border pt-4"><button class="rounded-md bg-brand-600 px-5 py-2 text-sm font-medium text-white">حفظ التعديل</button><a href="{{ route('maintenance.show', $maintenanceRequest) }}" class="rounded-md border border-border-strong bg-white px-5 py-2 text-sm">إلغاء</a></div>
    </form>
</div>
@endsection
