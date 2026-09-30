@extends('layouts.app')

@section('title', 'إعدادات الصيانة')

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6"><h2 class="text-xl font-semibold text-ink">إعدادات الصيانة</h2><p class="mt-1 text-sm text-ink-secondary">حدد الطبقات المكانية المتاحة للصيانة والأدوار التي يمكنها الوصول إليها. إضافة Layer جديدة لاحقًا لا تحتاج كودًا جديدًا.</p></div>
    <div class="card-institutional overflow-hidden"><div class="overflow-x-auto"><table class="table-institutional"><thead><tr><th>الطبقة</th><th>الحالة</th><th>المعالم</th><th>الأدوار المسموحة</th><th>حفظ</th></tr></thead><tbody>
    @forelse($datasets as $dataset)
        <tr>
            <td><div class="font-medium text-ink">{{ $dataset->display_name }}</div><div class="text-xs text-ink-muted">{{ $dataset->name }}</div></td>
            <td><form id="dataset-form-{{ $dataset->id }}" method="POST" action="{{ route('maintenance.settings.dataset', $dataset) }}">@csrf<input type="hidden" name="maintenance_enabled" value="0"></form><label class="inline-flex items-center gap-2 text-sm"><input form="dataset-form-{{ $dataset->id }}" type="checkbox" name="maintenance_enabled" value="1" @checked($dataset->maintenance_enabled)><span>مفعلة</span></label></td>
            <td>{{ number_format($dataset->gis_features_count) }}</td>
            <td><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">@foreach($roles as $role)<label class="inline-flex items-center gap-2 text-sm"><input form="dataset-form-{{ $dataset->id }}" type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked($dataset->maintenanceRoles->contains('id', $role->id))><span>{{ $role->name }}</span></label>@endforeach</div></td>
            <td><button form="dataset-form-{{ $dataset->id }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white">حفظ</button></td>
        </tr>
    @empty
        <tr><td colspan="5" class="py-10 text-center text-sm text-ink-muted">لا توجد طبقات مكانية.</td></tr>
    @endforelse
    </tbody></table></div></div>
</div>
@endsection
