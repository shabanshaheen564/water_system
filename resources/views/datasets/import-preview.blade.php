@extends('layouts.app')
@section('title', 'معاينة استيراد GIS')
@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-ink">معاينة استيراد GIS</h2>
        <p class="mt-1 text-sm text-ink-secondary">راجع خصائص الطبقة وحدد طريقة إدارتها قبل الاستيراد النهائي.</p>
    </div>
    <div class="card-institutional p-6">
        @if($errors->any())
            <div class="mb-4 rounded-md border border-danger/30 bg-danger/5 p-3 text-sm text-danger">{{ $errors->first() }}</div>
        @endif
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div><div class="text-xs text-ink-muted">Geometry</div><div class="mt-1 font-medium text-ink">{{ $info['geometry_type'] }}</div></div>
            <div><div class="text-xs text-ink-muted">Shapefile Type</div><div class="mt-1 font-medium text-ink">{{ $info['shape_type'] }}</div></div>
            <div><div class="text-xs text-ink-muted">Features</div><div class="mt-1 font-medium text-ink">{{ number_format($info['record_count']) }}</div></div>
            <div><div class="text-xs text-ink-muted">Detected CRS</div><div class="mt-1 font-medium text-ink">{{ $info['srid'] ? 'EPSG:'.$info['srid'] : 'غير محدد — اختره يدويًا' }}</div></div>
        </div>
        <form method="POST" action="{{ route('datasets.import.confirm') }}" class="mt-6 space-y-6">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div><label class="mb-1 block text-sm font-medium text-ink">اسم النظام</label><input name="name" required value="{{ old('name', IlluminateSupportStr::slug(pathinfo($info['shp'], PATHINFO_FILENAME), '_')) }}" class="input-institutional w-full px-3 py-2"><p class="mt-1 text-xs text-ink-muted">حروف إنجليزية وأرقام و _ فقط.</p></div>
                <div><label class="mb-1 block text-sm font-medium text-ink">اسم العرض</label><input name="display_name" required value="{{ old('display_name', pathinfo($info['shp'], PATHINFO_FILENAME)) }}" class="input-institutional w-full px-3 py-2"></div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">طريقة إدارة البيانات</label>
                <select name="management_mode" required class="input-institutional w-full px-3 py-2">
                    <option value="official">Official — بيانات رسمية من ArcGIS Pro</option>
                    <option value="web_editable">Web Editable — طبقة قابلة للرسم من الويب</option>
                    <option value="operational">Operational — بيانات تشغيلية</option>
                    <option value="analytical">Analytical — طبقة ناتجة عن تحليل مكاني</option>
                </select>
            </div>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div><label class="mb-1 block text-sm font-medium text-ink">CRS / SRID</label><input type="number" name="srid" required min="1" value="{{ old('srid', $info['srid']) }}" class="input-institutional w-full px-3 py-2"><p class="mt-1 text-xs text-ink-muted">إذا كان .prj غير قابل للتعرف، أدخل EPSG يدويًا. مثال Palestine Grid 1923 = 28191.</p></div>
                <div><label class="mb-1 block text-sm font-medium text-ink">مصدر البيانات</label><input name="source_name" value="{{ old('source_name') }}" class="input-institutional w-full px-3 py-2" placeholder="Municipality GIS / ArcGIS Pro"></div>
                <div><label class="mb-1 block text-sm font-medium text-ink">لون الطبقة</label><input type="color" name="display_color" value="#475467" class="h-10 w-full cursor-pointer rounded-md border p-1"></div>
            </div>
            <div><label class="mb-1 block text-sm font-medium text-ink">الوصف</label><textarea name="description" rows="2" class="input-institutional w-full px-3 py-2">{{ old('description') }}</textarea></div>
            <div>
                <h3 class="mb-2 text-sm font-semibold text-ink">الحقول المكتشفة</h3>
                <div class="overflow-x-auto"><table class="table-institutional"><thead><tr><th>الحقل</th><th>نوع DBF</th><th>نوع النظام</th><th>Decimals</th></tr></thead><tbody>
                @foreach($info['fields'] as $name => $field)<tr><td>{{ $name }}</td><td>{{ $field['type'] }}</td><td>{{ match($field['type']) { 'C'=>'string', 'L'=>'boolean', 'D'=>'date', 'N','F'=> (($field['decimals'] ?? 0) > 0 ? 'decimal' : 'integer'), default=>'string' } }}</td><td>{{ $field['decimals'] ?? 0 }}</td></tr>@endforeach
                </tbody></table></div>
            </div>
            <div class="rounded-md border border-brand-200 bg-brand-50 p-4 text-sm text-brand-900">
                <strong>CRS:</strong> سيتم حفظ البيانات بالـ SRID الأصلي. لا يتم تحويل الإحداثيات أثناء الاستيراد. التحويل إلى WGS84 يتم فقط عند إخراج GeoJSON وعرض الطبقة على الخريطة.
            </div>
            <div class="flex justify-end gap-3 border-t border-border pt-6">
                <a href="{{ route('datasets.import') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink">إلغاء</a>
                <button class="btn-motion rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white">استيراد الطبقة</button>
            </div>
        </form>
    </div>
</div>
@endsection
