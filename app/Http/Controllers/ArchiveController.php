<?php

namespace App\Http\Controllers;

use App\Models\ArchivedComplaint;
use App\Models\ArchivedWorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArchiveController extends Controller
{
    public function index(Request $request): View
    {
        $show = $request->input('show', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $complaints = ArchivedComplaint::with(['assignedTo:id,name'])
            ->when($dateFrom, fn ($q) => $q->whereDate('archived_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('archived_at', '<=', $dateTo))
            ->latest('archived_at')->paginate(10, ['*'], 'complaints_page')->withQueryString();

        $workOrders = ArchivedWorkOrder::with(['assignedTo:id,name'])
            ->when($dateFrom, fn ($q) => $q->whereDate('archived_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('archived_at', '<=', $dateTo))
            ->latest('archived_at')->paginate(10, ['*'], 'work_orders_page')->withQueryString();

        $stats = [
            'complaints_count' => ArchivedComplaint::query()->count(),
            'work_orders_count' => ArchivedWorkOrder::query()->count(),
            'avg_response_minutes' => ArchivedComplaint::query()->whereNotNull('response_time_minutes')->avg('response_time_minutes'),
            'avg_resolution_minutes' => ArchivedComplaint::query()->whereNotNull('resolution_time_minutes')->avg('resolution_time_minutes'),
            'avg_task_execution_minutes' => ArchivedWorkOrder::query()->whereNotNull('execution_time_minutes')->avg('execution_time_minutes'),
        ];

        return view('archive.index', compact('complaints', 'workOrders', 'stats', 'show', 'dateFrom', 'dateTo'));
    }

    public function complaints(Request $request): JsonResponse
    {
        $query = ArchivedComplaint::with(['reportedBy:id,name,email', 'assignedTo:id,name,email', 'processedBy:id,name,email', 'workOrders']);
        $this->applyFilters($query, $request, 'complaint');
        $items = $query->latest('archived_at')->paginate(min(max($request->integer('per_page', 20), 1), 100));
        return response()->json($items);
    }

    public function workOrders(Request $request): JsonResponse
    {
        $query = ArchivedWorkOrder::with(['assignedTo:id,name,email', 'createdBy:id,name,email', 'complaints']);
        $this->applyFilters($query, $request, 'work_order');
        $items = $query->latest('archived_at')->paginate(min(max($request->integer('per_page', 20), 1), 100));
        return response()->json($items);
    }

    public function summary(Request $request): JsonResponse
    {
        $complaints = ArchivedComplaint::query();
        $workOrders = ArchivedWorkOrder::query();
        $this->applyFilters($complaints, $request, 'complaint');
        $this->applyFilters($workOrders, $request, 'work_order');

        return response()->json([
            'complaints' => [
                'total' => (clone $complaints)->count(),
                'avg_response_time_minutes' => $this->roundedAvg($complaints, 'response_time_minutes'),
                'avg_resolution_time_minutes' => $this->roundedAvg($complaints, 'resolution_time_minutes'),
                'fastest_response_minutes' => (clone $complaints)->whereNotNull('response_time_minutes')->min('response_time_minutes'),
                'slowest_response_minutes' => (clone $complaints)->whereNotNull('response_time_minutes')->max('response_time_minutes'),
            ],
            'work_orders' => [
                'total' => (clone $workOrders)->count(),
                'avg_response_time_minutes' => $this->roundedAvg($workOrders, 'response_time_minutes'),
                'avg_execution_time_minutes' => $this->roundedAvg($workOrders, 'execution_time_minutes'),
                'avg_total_time_minutes' => $this->roundedAvg($workOrders, 'total_time_minutes'),
            ],
        ]);
    }

    private function applyFilters($query, Request $request, string $type): void
    {
        if ($request->filled('date_from')) $query->whereDate('archived_at', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $query->whereDate('archived_at', '<=', $request->input('date_to'));
        if ($request->filled('priority')) $query->where('priority', $request->input('priority'));
        if ($request->filled('assigned_to')) $query->where('assigned_to', $request->input('assigned_to'));
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search, $type) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
                if ($type === 'complaint') $q->orWhere('contact_name', 'ilike', "%{$search}%")->orWhere('complaint_number', 'ilike', "%{$search}%");
                else $q->orWhere('work_order_number', 'ilike', "%{$search}%");
            });
        }
    }

    private function roundedAvg($query, string $field): ?int
    {
        $value = (clone $query)->whereNotNull($field)->avg($field);
        return $value === null ? null : (int) round($value);
    }
}
