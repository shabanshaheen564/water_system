<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkOrder\StoreWorkOrderRequest;
use App\Http\Requests\WorkOrder\UpdateWorkOrderRequest;
use App\Models\Complaint;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\ArchiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WorkOrder::with(['complaints:id,complaint_number,title,status', 'assignedTo:id,name,email', 'createdBy:id,name,email']);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('priority')) $query->where('priority', $request->priority);
        if ($request->filled('assigned_to')) $query->where('assigned_to', $request->assigned_to);
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->input('date_to'));
        if ($request->filled('updated_from')) $query->whereDate('updated_at', '>=', $request->input('updated_from'));
        if ($request->filled('updated_to')) $query->whereDate('updated_at', '<=', $request->input('updated_to'));
        if ($request->filled('complaint_id')) $query->whereHas('complaints', fn ($complaints) => $complaints->whereKey($request->complaint_id));
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('work_order_number', 'ilike', "%{$search}%")
                    ->orWhere('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhereHas('complaints', fn ($complaints) => $complaints->where('complaint_number', 'ilike', "%{$search}%")->orWhere('title', 'ilike', "%{$search}%"));
            });
        }
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $workOrders = $query->orderByDesc('created_at')->paginate($perPage);
        $data = $workOrders->getCollection()->map(fn ($workOrder) => $this->formatWorkOrder($workOrder));
        return response()->json(['data' => $data, 'links' => ['first' => $workOrders->url(1), 'last' => $workOrders->url($workOrders->lastPage()), 'prev' => $workOrders->previousPageUrl(), 'next' => $workOrders->nextPageUrl()], 'meta' => ['current_page' => $workOrders->currentPage(), 'from' => $workOrders->firstItem(), 'last_page' => $workOrders->lastPage(), 'path' => $workOrders->path(), 'per_page' => $workOrders->perPage(), 'to' => $workOrders->lastItem(), 'total' => $workOrders->total()]]);
    }

    public function store(StoreWorkOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $idempotencyKey = $validated['idempotency_key'] ?? null;

        if ($idempotencyKey !== null) {
            $existing = WorkOrder::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('created_by', $request->user()->id)
                ->first();

            if ($existing) {
                $existing->load(['complaints:id,complaint_number,title,status', 'assignedTo:id,name,email', 'createdBy:id,name,email']);
                return response()->json($this->formatWorkOrder($existing), 200);
            }
        }

        if (array_key_exists('assigned_to', $validated) && $validated['assigned_to'] !== null) {
            abort_unless($request->user()->can('tasks.assign'), 403);
            $this->validateUserActive($validated['assigned_to']);
        }
        return DB::transaction(function () use ($validated, $request) {
            $workOrder = WorkOrder::create(['work_order_number' => $this->generateWorkOrderNumber(), 'title' => $validated['title'], 'description' => $validated['description'], 'status' => $validated['status'] ?? 'pending', 'priority' => $validated['priority'] ?? 'medium', 'assigned_to' => $validated['assigned_to'] ?? null, 'created_by' => $request->user()->id, 'notes' => $validated['notes'] ?? null]);
            if (!empty($validated['complaint_id'])) $workOrder->complaints()->syncWithoutDetaching([$validated['complaint_id']]);
            if ($workOrder->status === 'in_progress' && ! $workOrder->started_at) $workOrder->update(['started_at' => now()]);
            $workOrder->load(['complaints:id,complaint_number,title,status', 'assignedTo:id,name,email', 'createdBy:id,name,email']);
            return response()->json($this->formatWorkOrder($workOrder), 201);
        });
    }

    public function show(WorkOrder $workOrder): JsonResponse
    {
        $workOrder->load(['complaints:id,complaint_number,title,status', 'assignedTo:id,name,email', 'createdBy:id,name,email']);
        return response()->json($this->formatWorkOrder($workOrder));
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder, ArchiveService $archive): JsonResponse
    {
        $validated = $request->validated();
        abort_unless($validated !== [], 422);
        unset($validated['work_order_number'], $validated['created_by'], $validated['started_at'], $validated['completed_at']);
        if (array_key_exists('assigned_to', $validated)) {
            abort_unless($request->user()->can('tasks.assign'), 403);
            if ($validated['assigned_to'] !== null) $this->validateUserActive($validated['assigned_to']);
        }
        if (array_key_exists('status', $validated)) abort_unless($request->user()->can('tasks.transition'), 403);
        foreach (['title', 'description', 'priority', 'notes', 'latitude', 'longitude'] as $field) if (array_key_exists($field, $validated)) abort_unless($request->user()->can('tasks.update'), 403);
        $oldStatus = $workOrder->status;
        $payload = DB::transaction(function () use ($validated, $workOrder, $oldStatus, $request) {
            $workOrder->update($validated);
            if (isset($validated['status']) && $validated['status'] !== $oldStatus) $this->handleStatusTransition($workOrder, $oldStatus, $validated['status'], $request->user()->id);
            $workOrder->load(['complaints:id,complaint_number,title,status', 'assignedTo:id,name,email', 'createdBy:id,name,email']);
            return $this->formatWorkOrder($workOrder);
        });

        if (($validated['status'] ?? $oldStatus) === 'completed') {
            $workOrder->load('complaints');
            $complaintIds = $workOrder->complaints->pluck('id')->all();
            $archive->archiveCompletedWorkOrder($workOrder);
            foreach ($complaintIds as $complaintId) {
                $complaint = Complaint::find($complaintId);
                if ($complaint?->status === 'closed') $archive->archiveClosedComplaint($complaint);
            }
        }

        return response()->json($payload);
    }

    public function destroy(WorkOrder $workOrder): JsonResponse
    {
        if ($workOrder->complaints()->exists()) return response()->json(['message' => 'Cannot delete a work order that has linked complaints.'], 422);
        $workOrder->delete();
        return response()->json(['message' => 'Work order deleted successfully.']);
    }

    public function convertToComplaint(Request $request, WorkOrder $workOrder): JsonResponse
    {
        abort_unless($request->user()->can('tasks.update') && $request->user()->can('complaints.create'), 403);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        return DB::transaction(function () use ($validated, $workOrder, $request) {
            $complaint = Complaint::create([
                'complaint_number' => $this->generateComplaintNumber(),
                'title' => $validated['title'] ?? $workOrder->title,
                'description' => $validated['description'] ?? $workOrder->description,
                'status' => 'open',
                'priority' => $workOrder->priority,
                'reported_by' => $request->user()->id,
                'assigned_to' => $workOrder->assigned_to,
                'contact_name' => $validated['contact_name'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'latitude' => $workOrder->latitude,
                'longitude' => $workOrder->longitude,
            ]);

            $workOrder->complaints()->attach($complaint->id);

            if ($workOrder->status === 'completed') {
                $complaint->update(['status' => 'closed', 'resolved_at' => now(), 'processed_by' => $request->user()->id, 'processed_at' => now(), 'first_response_at' => now()]);
            } elseif (! in_array($workOrder->status, ['cancelled', 'pending'], true)) {
                $complaint->update(['status' => 'in_progress', 'processed_by' => $request->user()->id, 'processed_at' => now(), 'first_response_at' => $complaint->first_response_at ?? now()]);
            }

            $complaint->load(['reportedBy:id,name,email','assignedTo:id,name,email','workOrders']);
            return response()->json([
                'message' => 'Work order converted to complaint successfully.',
                'complaint' => [
                    'id' => $complaint->id,
                    'complaint_number' => $complaint->complaint_number,
                    'title' => $complaint->title,
                    'status' => $complaint->status,
                    'priority' => $complaint->priority,
                    'latitude' => $complaint->latitude ? (string) $complaint->latitude : null,
                    'longitude' => $complaint->longitude ? (string) $complaint->longitude : null,
                    'work_orders' => $complaint->workOrders->map(fn ($wo) => ['id' => $wo->id, 'work_order_number' => $wo->work_order_number, 'status' => $wo->status])->values(),
                ],
            ], 201);
        });
    }

    public function addComplaint(Request $request, WorkOrder $workOrder): JsonResponse
    {
        abort_unless($request->user()->can('tasks.update') && $request->user()->can('complaints.update'), 403);
        $validated = $request->validate(['complaint_id' => ['required', 'exists:complaints,id']]);
        return DB::transaction(function () use ($validated, $workOrder, $request) {
            $workOrder = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            if (in_array($workOrder->status, ['completed', 'cancelled'], true)) return response()->json(['message' => 'Cannot add a complaint to a completed or cancelled work order.'], 422);
            $complaint = Complaint::findOrFail($validated['complaint_id']);
            if (!$workOrder->complaints()->whereKey($complaint->id)->exists()) $workOrder->complaints()->attach($complaint->id);
            $complaint->update(['status' => 'in_progress', 'assigned_to' => $workOrder->assigned_to, 'processed_by' => $request->user()->id, 'processed_at' => now(), 'first_response_at' => $complaint->first_response_at ?? now()]);
            $workOrder->load(['complaints:id,complaint_number,title,status', 'assignedTo:id,name,email', 'createdBy:id,name,email']);
            return response()->json($this->formatWorkOrder($workOrder));
        });
    }

    private function generateComplaintNumber(): string
    {
        $nextNumber = DB::selectOne("SELECT nextval('complaints_number_seq') as next_number")->next_number;
        return 'CMP-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
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
        $validTransitions = ['pending' => ['assigned', 'cancelled'], 'assigned' => ['in_progress', 'completed', 'cancelled', 'pending'], 'in_progress' => ['completed', 'cancelled', 'assigned'], 'completed' => ['in_progress'], 'cancelled' => ['pending']];
        if (isset($validTransitions[$oldStatus]) && ! in_array($newStatus, $validTransitions[$oldStatus], true)) abort(422, "Invalid status transition from {$oldStatus} to {$newStatus}.");
        if ($newStatus === 'in_progress' && ! $workOrder->started_at) $workOrder->update(['started_at' => now()]);
        if ($newStatus === 'completed' && ! $workOrder->completed_at) {
            if (! $workOrder->started_at) $workOrder->update(['started_at' => now()]);
            $workOrder->update(['completed_at' => now()]);
            $workOrder->loadMissing('complaints');
            foreach ($workOrder->complaints as $complaint) {
                if ($complaint->status === 'cancelled') continue;
                $hasIncompleteWorkOrders = $complaint->workOrders()->where('status', '<>', 'completed')->exists();
                if (!$hasIncompleteWorkOrders) $complaint->update(['status' => 'closed', 'resolved_at' => $complaint->resolved_at ?? now(), 'processed_by' => $processedBy, 'processed_at' => now(), 'first_response_at' => $complaint->first_response_at ?? now()]);
            }
        }
    }

    private function formatWorkOrder(WorkOrder $workOrder): array
    {
        $complaints = $workOrder->complaints->map(fn ($complaint) => ['id' => $complaint->id, 'complaint_number' => $complaint->complaint_number, 'title' => $complaint->title, 'status' => $complaint->status])->values()->all();
        return ['id' => $workOrder->id, 'work_order_number' => $workOrder->work_order_number, 'complaints' => $complaints, 'complaint' => $complaints[0] ?? null, 'title' => $workOrder->title, 'description' => $workOrder->description, 'status' => $workOrder->status, 'priority' => $workOrder->priority, 'assigned_to' => $workOrder->assignedTo ? ['id' => $workOrder->assignedTo->id, 'name' => $workOrder->assignedTo->name, 'email' => $workOrder->assignedTo->email] : null, 'created_by' => $workOrder->createdBy ? ['id' => $workOrder->createdBy->id, 'name' => $workOrder->createdBy->name, 'email' => $workOrder->createdBy->email] : null, 'started_at' => $workOrder->started_at?->toISOString(), 'completed_at' => $workOrder->completed_at?->toISOString(), 'notes' => $workOrder->notes, 'created_at' => $workOrder->created_at?->toISOString(), 'updated_at' => $workOrder->updated_at?->toISOString()];
    }
}