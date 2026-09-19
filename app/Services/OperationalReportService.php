<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\WorkOrder;
use App\Models\ArchivedComplaint;
use App\Models\ArchivedWorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OperationalReportService
{
    public function complaintQuery(Request $request): Builder
    {
        $query = Complaint::with(['reportedBy:id,name,email', 'assignedTo:id,name,email', 'processedBy:id,name,email', 'workOrders:id,work_order_number,title,status,priority']);

        $filters = [
            'status' => $request->input('complaint_status', $request->input('status')),
            'priority' => $request->input('complaint_priority', $request->input('priority')),
            'assigned_to' => $request->input('complaint_assigned_to', $request->input('assigned_to')),
            'reported_by' => $request->input('reported_by'),
        ];
        foreach ($filters as $field => $value) if ($value !== null && $value !== '') $query->where($field, $value);

        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->input('date_to'));

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('complaint_number', 'ilike', "%{$search}%")
                    ->orWhere('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhere('contact_name', 'ilike', "%{$search}%")
                    ->orWhere('contact_phone', 'ilike', "%{$search}%")
                    ->orWhere('address', 'ilike', "%{$search}%");
            });
        }

        return $query;
    }

    public function workOrderQuery(Request $request): Builder
    {
        $query = WorkOrder::with(['complaints:id,complaint_number,title,status', 'assignedTo:id,name,email', 'createdBy:id,name,email']);

        $filters = [
            'status' => $request->input('task_status', $request->input('status')),
            'priority' => $request->input('task_priority', $request->input('priority')),
            'assigned_to' => $request->input('task_assigned_to', $request->input('assigned_to')),
        ];
        foreach ($filters as $field => $value) if ($value !== null && $value !== '') $query->where($field, $value);

        if ($request->filled('complaint_id')) {
            $query->whereHas('complaints', fn ($q) => $q->whereKey($request->input('complaint_id')));
        }

        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->input('date_to'));

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('work_order_number', 'ilike', "%{$search}%")
                    ->orWhere('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhereHas('complaints', fn ($complaints) => $complaints
                        ->where('complaint_number', 'ilike', "%{$search}%")
                        ->orWhere('title', 'ilike', "%{$search}%"));
            });
        }

        return $query;
    }

    public function summary(Request $request): array
    {
        $complaints = $this->complaintQuery($request);
        $tasks = $this->workOrderQuery($request);
        $archivedComplaints = $this->archivedComplaintQuery($request);
        $archivedWorkOrders = $this->archivedWorkOrderQuery($request);

        return [
            'filters' => [
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'complaint_status' => $request->input('complaint_status'),
                'complaint_priority' => $request->input('complaint_priority'),
                'complaint_assigned_to' => $request->input('complaint_assigned_to'),
                'task_status' => $request->input('task_status'),
                'task_priority' => $request->input('task_priority'),
                'task_assigned_to' => $request->input('task_assigned_to'),
            ],
            'complaints' => [
                'total' => (clone $complaints)->count(),
                'open' => (clone $complaints)->where('status', 'open')->count(),
                'in_progress' => (clone $complaints)->where('status', 'in_progress')->count(),
                'resolved' => (clone $complaints)->where('status', 'resolved')->count(),
                'closed' => (clone $complaints)->where('status', 'closed')->count(),
                'cancelled' => (clone $complaints)->where('status', 'cancelled')->count(),
                'urgent' => (clone $complaints)->where('priority', 'urgent')->count(),
                'archived' => (clone $archivedComplaints)->count(),
                'total_with_archive' => (clone $complaints)->count() + (clone $archivedComplaints)->count(),
            ],
            'tasks' => [
                'total' => (clone $tasks)->count(),
                'pending' => (clone $tasks)->where('status', 'pending')->count(),
                'assigned' => (clone $tasks)->where('status', 'assigned')->count(),
                'in_progress' => (clone $tasks)->where('status', 'in_progress')->count(),
                'completed' => (clone $tasks)->where('status', 'completed')->count(),
                'cancelled' => (clone $tasks)->where('status', 'cancelled')->count(),
                'urgent' => (clone $tasks)->where('priority', 'urgent')->count(),
                'archived' => (clone $archivedWorkOrders)->count(),
                'total_with_archive' => (clone $tasks)->count() + (clone $archivedWorkOrders)->count(),
            ],
            'performance' => [
                'archived_complaints' => [
                    'average_response_time_minutes' => $this->roundedAverage($archivedComplaints, 'response_time_minutes'),
                    'average_resolution_time_minutes' => $this->roundedAverage($archivedComplaints, 'resolution_time_minutes'),
                    'fastest_response_minutes' => (clone $archivedComplaints)->whereNotNull('response_time_minutes')->min('response_time_minutes'),
                    'slowest_response_minutes' => (clone $archivedComplaints)->whereNotNull('response_time_minutes')->max('response_time_minutes'),
                ],
                'archived_work_orders' => [
                    'average_response_time_minutes' => $this->roundedAverage($archivedWorkOrders, 'response_time_minutes'),
                    'average_execution_time_minutes' => $this->roundedAverage($archivedWorkOrders, 'execution_time_minutes'),
                    'average_total_time_minutes' => $this->roundedAverage($archivedWorkOrders, 'total_time_minutes'),
                ],
            ],
        ];
    }


    public function archivedComplaintQuery(Request $request): Builder
    {
        $query = ArchivedComplaint::query();
        if ($request->filled('date_from')) $query->whereDate('original_created_at', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $query->whereDate('original_created_at', '<=', $request->input('date_to'));
        if ($request->filled('complaint_priority')) $query->where('priority', $request->input('complaint_priority'));
        if ($request->filled('complaint_assigned_to')) $query->where('assigned_to', $request->input('complaint_assigned_to'));
        return $query;
    }

    public function archivedWorkOrderQuery(Request $request): Builder
    {
        $query = ArchivedWorkOrder::query();
        if ($request->filled('date_from')) $query->whereDate('archived_at', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $query->whereDate('archived_at', '<=', $request->input('date_to'));
        if ($request->filled('task_priority')) $query->where('priority', $request->input('task_priority'));
        if ($request->filled('task_assigned_to')) $query->where('assigned_to', $request->input('task_assigned_to'));
        return $query;
    }

    private function roundedAverage($query, string $field): ?int
    {
        $value = (clone $query)->whereNotNull($field)->avg($field);
        return $value === null ? null : (int) round($value);
    }

    public function filters(): array
    {
        return [
            'complaint_statuses' => [
                ['value' => 'open', 'label' => 'جديدة'],
                ['value' => 'in_progress', 'label' => 'قيد المعالجة'],
                ['value' => 'resolved', 'label' => 'تم الحل'],
                ['value' => 'closed', 'label' => 'مغلقة'],
                ['value' => 'cancelled', 'label' => 'ملغاة'],
            ],
            'work_order_statuses' => [
                ['value' => 'pending', 'label' => 'معلقة'],
                ['value' => 'assigned', 'label' => 'مسندة'],
                ['value' => 'in_progress', 'label' => 'قيد التنفيذ'],
                ['value' => 'completed', 'label' => 'مكتملة'],
                ['value' => 'cancelled', 'label' => 'ملغاة'],
            ],
            'priorities' => [
                ['value' => 'low', 'label' => 'منخفضة'],
                ['value' => 'medium', 'label' => 'متوسطة'],
                ['value' => 'high', 'label' => 'عالية'],
                ['value' => 'urgent', 'label' => 'عاجلة'],
            ],
            'users' => \App\Models\User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email'])->values(),
            'date_fields' => ['created_at', 'updated_at', 'resolved_at', 'processed_at'],
        ];
    }
}
