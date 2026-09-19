<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#222;line-height:1.7}h1{font-size:20px;margin-bottom:4px}h2{font-size:14px;margin-top:20px;border-bottom:1px solid #bbb;padding-bottom:5px}.meta{color:#666}.grid{width:100%;border-collapse:collapse}.grid td,.grid th{border:1px solid #ccc;padding:7px;text-align:right}.grid th{background:#f3f4f6;width:22%}.header{border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:15px}
</style></head><body>
<div class="header"><h1>تقرير مهمة عمل</h1><div class="meta">نظام إدارة المياه والصرف الصحي</div></div>
<table class="grid">
<tr><th>رقم المهمة</th><td>{{ $workOrder->work_order_number }}</td><th>الحالة</th><td>{{ $workOrder->status }}</td></tr>
<tr><th>العنوان</th><td colspan="3">{{ $workOrder->title }}</td></tr>
<tr><th>الأولوية</th><td>{{ $workOrder->priority }}</td><th>تاريخ الإنشاء</th><td>{{ optional($workOrder->created_at)->format('Y-m-d H:i') }}</td></tr>
<tr><th>الوصف</th><td colspan="3">{{ $workOrder->description }}</td></tr>
<tr><th>الملاحظات</th><td colspan="3">{{ $workOrder->notes ?: '—' }}</td></tr>
<tr><th>المسند إليه</th><td>{{ optional($workOrder->assignedTo)->name ?: '—' }}</td><th>أنشأها</th><td>{{ optional($workOrder->createdBy)->name ?: '—' }}</td></tr>
<tr><th>بدأت</th><td>{{ optional($workOrder->started_at)->format('Y-m-d H:i') ?: '—' }}</td><th>اكتملت</th><td>{{ optional($workOrder->completed_at)->format('Y-m-d H:i') ?: '—' }}</td></tr>
<tr><th>الموقع</th><td colspan="3">{{ $workOrder->latitude ?: '—' }}, {{ $workOrder->longitude ?: '—' }}</td></tr>
</table>
<h2>الشكاوى المرتبطة</h2>
<table class="grid"><tr><th>رقم الشكوى</th><th>العنوان</th><th>الحالة</th><th>الأولوية</th></tr>
@forelse($workOrder->complaints as $complaint)<tr><td>{{ $complaint->complaint_number }}</td><td>{{ $complaint->title }}</td><td>{{ $complaint->status }}</td><td>{{ $complaint->priority }}</td></tr>@empty<tr><td colspan="4">لا توجد شكاوى مرتبطة.</td></tr>@endforelse
</table>
</body></html>
