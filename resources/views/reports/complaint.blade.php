<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#222;line-height:1.7}h1{font-size:20px;margin-bottom:4px}h2{font-size:14px;margin-top:20px;border-bottom:1px solid #bbb;padding-bottom:5px}.meta{color:#666}.grid{width:100%;border-collapse:collapse}.grid td,.grid th{border:1px solid #ccc;padding:7px;text-align:right}.grid th{background:#f3f4f6;width:22%}.header{border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:15px}
</style></head><body>
<div class="header"><h1>تقرير شكوى</h1><div class="meta">نظام إدارة المياه والصرف الصحي</div></div>
<table class="grid">
<tr><th>رقم الشكوى</th><td>{{ $complaint->complaint_number }}</td><th>الحالة</th><td>{{ $complaint->status }}</td></tr>
<tr><th>العنوان</th><td colspan="3">{{ $complaint->title }}</td></tr>
<tr><th>الأولوية</th><td>{{ $complaint->priority }}</td><th>تاريخ الإنشاء</th><td>{{ optional($complaint->created_at)->format('Y-m-d H:i') }}</td></tr>
<tr><th>المواطن</th><td>{{ $complaint->contact_name ?: '—' }}</td><th>الهاتف</th><td>{{ $complaint->contact_phone ?: '—' }}</td></tr>
<tr><th>العنوان</th><td colspan="3">{{ $complaint->address ?: '—' }}</td></tr>
<tr><th>الوصف</th><td colspan="3">{{ $complaint->description }}</td></tr>
<tr><th>ملاحظات المعالجة</th><td colspan="3">{{ $complaint->processing_notes ?: '—' }}</td></tr>
<tr><th>الحل</th><td colspan="3">{{ $complaint->solution ?: '—' }}</td></tr>
<tr><th>المسند إليه</th><td>{{ optional($complaint->assignedTo)->name ?: '—' }}</td><th>المعالج</th><td>{{ optional($complaint->processedBy)->name ?: '—' }}</td></tr>
<tr><th>الموقع</th><td colspan="3">{{ $complaint->latitude ?: '—' }}, {{ $complaint->longitude ?: '—' }}</td></tr>
</table>
<h2>المهام المرتبطة</h2>
<table class="grid"><tr><th>رقم المهمة</th><th>العنوان</th><th>الحالة</th><th>الأولوية</th></tr>
@forelse($complaint->workOrders as $workOrder)<tr><td>{{ $workOrder->work_order_number }}</td><td>{{ $workOrder->title }}</td><td>{{ $workOrder->status }}</td><td>{{ $workOrder->priority }}</td></tr>@empty<tr><td colspan="4">لا توجد مهام مرتبطة.</td></tr>@endforelse
</table>
</body></html>
