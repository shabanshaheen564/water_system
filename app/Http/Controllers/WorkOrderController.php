<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkOrder\StoreWorkOrderRequest;
use App\Http\Requests\WorkOrder\UpdateWorkOrderRequest;
use App\Models\Complaint;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WorkOrder::with([
            'complaint:id,complaint_number,title',
            'complaints:id,complaint_number,title,status',
            'assignedTo:id,name,email',
            'createdBy:id,name,email',
        ]);

        if ($request->has('status')) $query->where('status', $request->status);
        if ($request->has('priority')) $query->where('priority', $request->priority);
        if ($request->has('assigned_to')) $query->where('assigned_to', $request->assigned_to);
        if ($request->has('complaint_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('complaint_id', $request->complaint_id)
                    ->orWhereHas('complaints', fn ($complaints) => $complaints->whereKey($request->complaint_id));
            });
        }

        $workOrders = $query->orderBy('created_at', 'desc')->paginate();
        $data = $workOrders->getCollection()->map(fn ($workOrder) => $this->formatWorkOrder($workOrder));

        return response()->json([
            'data' => $data,
            'links' => [
                'first' => $workOrders->url(1),
                'last' => $workOrders->url($workOrders->lastPage()),
                'prev' => $workOrders->previousPageUrl(),
                'next' => $workOrders->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $workOrders->currentPage(),
                'from' => $workOrders->firstItem(),
                'last_page' => $workOrders->lastPage(),
                'path' => $workOrders->path(),
                'per_page' => $workOrders->perPage(),
                'to' => $workOrders->lastItem(),
                'total' => $workOrders->total(),
            ],
        ]);
    }

    public function store(StoreWorkOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $request) {
            $workOrder = WorkOrder::create([
                'work_order_number' => $this->generateWorkOrderNumber(),
                'complaint_id' => $validated['complaint_id'] ?? null,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'status' => $validated['status'] ?? 'pending',
                'priority' => $validated['priority'] ?? 'medium',
                'assigned_to' => $validated['assigned_to'] ?? null,
                'created_by' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($workOrder->assigned_to) $this->validateUserActive($workOrder->assigned_to);
            if ($workOrder->complaint_id) $workOrder->complaints()->syncWithoutDetaching([$workOrder->complaint_id]);
            if ($workOrder->status === 'in_progress' && ! $workOrder->started_at) $workOrder->update(['started_at' => now()]);

            $workOrder->load(['complaint:id,complaint_number,title', 'complaints:id,complaint_number,title,status', 'assignedTo:id,name,email', 'createdBy:id,name,email']);
            return response()->json($this->formatWorkOrder($workOrder), 201);
        });
    }

    public function show(WorkOrder $workOrder): JsonResponse
    {
        $workOrder->load(['complaint:id,complaint_number,title', 'complaints:id,complaint_number,title,status', 'assignedTo:id,name,email', 'createdBy:id,name,email']);
        return response()->json($this->formatWorkOrder($workOrder));
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validated();
        unset($validated['work_order_number'], $validated['created_by'], $validated['created_at'], $validated['updated_at'], $validated['started_at'], $validated['completed_at']);
        $oldStatus = $workOrder->status;

        return DB::transaction(function () use ($validated, $workOrder, $oldStatus, $request) {
            if (isset($validated['assigned_to'])) $this->validateUserActive($validated['assigned_to']);
            if (isset($validated['complaint_id'])) Complaint::findOrFail($validated['complaint_id']);

            $workOrder->update($validated);
            if (isset($validated['complaint_id'])) $workOrder->complaints()->syncWithoutDetaching([$validated['complaint_id']]);
            if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
                $this->handleStatusTransition($workOrder, $oldStatus, $validated['status'], $request->user()->id);
            }

            $workOrder->load(['complaint:id,complaint_number,title', 'complaints:id,complaint_number,title,status', 'assignedTo:id,name,email', 'createdBy:id,name,email']);
            return response()->json($this->formatWorkOrder($workOrder));
        });
    }

    private function generateWorkOrderNumber(): string
    {
        $nextNumber = DB::selectOne("SELECT nextval('work_orders_number_seq') as next_number")->next_number;
        return 'WO-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    private function validateUserActive(int $userId): void
    {
        $user = User::find($userId);
        if ($user && ! $user->is_active) abort(422, 'Cannot assign to inactive user.');
    }

    private function handleStatusTransition(WorkOrder $workOrder, string $oldStatus, string $newStatus, int $processedBy): void
    {
        $validTransitions = [
            'pending' => ['assigned', 'cancelled'],
            'assigned' => ['in_progress', 'cancelled', 'pending'],
            'in_progress' => ['completed', 'cancelled', 'assigned'],
            'completed' => ['in_progress'],
            'cancelled' => ['pending'],
        ];

        if (isset($validTransitions[$oldStatus]) && ! in_array($newStatus, $validTransitions[$oldStatus], true)) {
            abort(422, "Invalid status transition from {$oldStatus} to {$newStatus}.");
        }

        if ($newStatus === 'in_progress' && ! $workOrder->started_at) $workOrder->update(['started_at' => now()]);

        if ($newStatus === 'completed' && ! $workOrder->completed_at) {
            $workOrder->update(['completed_at' => now()]);
            $workOrder->loadMissing('complaints');
            $workOrder->complaints()->update([
                'status' => 'closed',
                'resolved_at' => now(),
                'processed_by' => $processedBy,
                'processed_at' => now(),
            ]);
        }
    }

    private function formatWorkOrder(WorkOrder $workOrder): array
    {
        return [
            'id' => $workOrder->id,
            'work_order_number' => $workOrder->work_order_number,
            'complaint' => $workOrder->complaint ? ['id' => $workOrder->complaint->id, 'complaint_number' => $workOrder->complaint->complaint_number, 'title' => $workOrder->complaint->title] : null,
            'complaints' => $workOrder->complaints->map(fn ($complaint) => ['id' => $complaint->id, 'complaint_number' => $complaint->complaint_number, 'title' => $complaint->title, 'status' => $complaint->status])->values()->all(),
            'title' => $workOrder->title,
            'description' => $workOrder->description,
            'status' => $workOrder->status,
            'priority' => $workOrder->priority,
            'assigned_to' => $workOrder->assignedTo ? ['id' => $workOrder->assignedTo->id, 'name' => $workOrder->assignedTo->name, 'email' => $workOrder->assignedTo->email] : null,
            'created_by' => $workOrder->createdBy ? ['id' => $workOrder->createdBy->id, 'name' => $workOrder->createdBy->name, 'email' => $workOrder->createdBy->email] : null,
            'started_at' => $workOrder->started_at?->toISOString(),
            'completed_at' => $workOrder->completed_at?->toISOString(),
            'notes' => $workOrder->notes,
            'created_at' => $workOrder->created_at?->toISOString(),
            'updated_at' => $workOrder->updated_at?->toISOString(),
        ];
    }
}
