<?php

namespace App\Http\Controllers;

use App\Http\Requests\Complaint\StoreComplaintRequest;
use App\Http\Requests\Complaint\UpdateComplaintRequest;
use App\Models\Complaint;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\ArchiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplaintController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Complaint::with(['reportedBy:id,name,email', 'assignedTo:id,name,email']);
        foreach (['status', 'priority', 'assigned_to', 'reported_by'] as $filter) {
            if ($request->filled($filter)) $query->where($filter, $request->{$filter});
        }
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->input('date_to'));
        if ($request->filled('updated_from')) $query->whereDate('updated_at', '>=', $request->input('updated_from'));
        if ($request->filled('updated_to')) $query->whereDate('updated_at', '<=', $request->input('updated_to'));
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('complaint_number', 'ilike', "%{$search}%")
                    ->orWhere('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhere('contact_name', 'ilike', "%{$search}%")
                    ->orWhere('contact_phone', 'ilike', "%{$search}%");
            });
        }
        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $complaints = $query->orderByDesc('created_at')->paginate($perPage);
        $data = $complaints->getCollection()->map(fn ($complaint) => $this->formatComplaint($complaint));
        return response()->json([
            'data' => $data,
            'links' => ['first' => $complaints->url(1), 'last' => $complaints->url($complaints->lastPage()), 'prev' => $complaints->previousPageUrl(), 'next' => $complaints->nextPageUrl()],
            'meta' => ['current_page' => $complaints->currentPage(), 'from' => $complaints->firstItem(), 'last_page' => $complaints->lastPage(), 'path' => $complaints->path(), 'per_page' => $complaints->perPage(), 'to' => $complaints->lastItem(), 'total' => $complaints->total()],
        ]);
    }

    public function store(StoreComplaintRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $reportedBy = $request->user()->id;
        if (array_key_exists('assigned_to', $validated) && $validated['assigned_to'] !== null) {
            abort_unless($request->user()->can('complaints.update'), 403);
            $this->validateUserActive($validated['assigned_to']);
        }
        return DB::transaction(function () use ($validated, $reportedBy) {
            $complaint = Complaint::create([
                'complaint_number' => $this->generateComplaintNumber(), 'title' => $validated['title'], 'description' => $validated['description'],
                'status' => 'open', 'priority' => $validated['priority'] ?? 'medium', 'reported_by' => $reportedBy,
                'assigned_to' => $validated['assigned_to'] ?? null, 'contact_name' => $validated['contact_name'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null, 'address' => $validated['address'] ?? null,
                'latitude' => $validated['latitude'] ?? null, 'longitude' => $validated['longitude'] ?? null,
            ]);
            $complaint->load(['reportedBy:id,name,email', 'assignedTo:id,name,email']);
            return response()->json($this->formatComplaint($complaint), 201);
        });
    }

    public function show(Complaint $complaint): JsonResponse
    {
        $complaint->load(['reportedBy:id,name,email', 'assignedTo:id,name,email', 'processedBy:id,name,email', 'workOrders']);
        return response()->json($this->formatComplaint($complaint, true));
    }

    public function update(UpdateComplaintRequest $request, Complaint $complaint, ArchiveService $archive): JsonResponse
    {
        $validated = $request->validated();
        abort_unless($validated !== [], 422);

        // These are immutable server-managed fields. They are intentionally ignored rather than updated.
        unset($validated['complaint_number'], $validated['reported_by'], $validated['resolved_at']);

        if (array_key_exists('status', $validated)) {
            abort_unless($request->user()->can('complaints.transition'), 403);
            $oldStatus = $complaint->status;
            if ($validated['status'] !== $oldStatus) {
                $validTransitions = [
                    'open' => ['in_progress', 'cancelled'], 'in_progress' => ['resolved', 'cancelled', 'open'],
                    'resolved' => ['closed', 'in_progress'], 'closed' => [], 'cancelled' => ['open'],
                ];
                if (! in_array($validated['status'], $validTransitions[$oldStatus] ?? [], true)) abort(422, "Invalid status transition from {$oldStatus} to {$validated['status']}.");
            }
        }

        $updateFields = ['title', 'description', 'processing_notes', 'solution', 'priority', 'assigned_to', 'contact_name', 'contact_phone', 'address', 'latitude', 'longitude'];
        foreach ($updateFields as $field) if (array_key_exists($field, $validated)) abort_unless($request->user()->can('complaints.update'), 403);
        if (array_key_exists('assigned_to', $validated) && $validated['assigned_to'] !== null) $this->validateUserActive($validated['assigned_to']);

        if (array_key_exists('processing_notes', $validated) || array_key_exists('solution', $validated) || in_array($validated['status'] ?? null, ['in_progress', 'resolved'], true)) {
            $validated['processed_by'] = $request->user()->id;
            $validated['processed_at'] = now();
            if (!$complaint->first_response_at) $validated['first_response_at'] = now();
        }
        if (($validated['status'] ?? null) === 'resolved' && !$complaint->resolved_at) {
            $validated['resolved_at'] = now();
            $validated['processed_by'] ??= $request->user()->id;
            $validated['processed_at'] ??= now();
        }
        $complaint->update($validated);
        $complaint->load(['reportedBy:id,name,email', 'assignedTo:id,name,email', 'processedBy:id,name,email']);
        $payload = $this->formatComplaint($complaint);
        if ($complaint->status === 'closed') $archive->archiveEligibleForComplaint($complaint);
        return response()->json($payload);
    }

    public function destroy(Complaint $complaint): JsonResponse
    {
        if ($complaint->workOrders()->exists()) return response()->json(['message' => 'Cannot delete a complaint that is linked to a work order.'], 422);
        $complaint->delete();
        return response()->json(['message' => 'Complaint deleted successfully.']);
    }

    public function convertToWorkOrder(Request $request, Complaint $complaint): JsonResponse
    {
        abort_unless($request->user()->can('complaints.convert_to_task'), 403);
        if ($complaint->workOrders()->exists()) return response()->json(['message' => 'This complaint is already linked to a work order.'], 422);
        $validated = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string'], 'priority' => ['required', 'in:low,medium,high,urgent'], 'assigned_to' => ['required', 'exists:users,id'], 'notes' => ['nullable', 'string']]);
        $this->validateUserActive((int) $validated['assigned_to']);
        return DB::transaction(function () use ($validated, $complaint, $request) {
            $nextNumber = DB::selectOne("SELECT nextval('work_orders_number_seq') AS next_number")->next_number;
            $workOrder = WorkOrder::create(['work_order_number' => 'WO-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT), 'title' => $validated['title'], 'description' => $validated['description'], 'status' => 'assigned', 'priority' => $validated['priority'], 'assigned_to' => $validated['assigned_to'], 'created_by' => $request->user()->id, 'notes' => $validated['notes'] ?? null]);
            $workOrder->complaints()->attach($complaint->id);
            $complaint->update(['status' => 'in_progress', 'assigned_to' => $validated['assigned_to'], 'processed_by' => $request->user()->id, 'processed_at' => now(), 'first_response_at' => $complaint->first_response_at ?? now()]);
            $workOrder->load(['complaints:id,complaint_number,title,status', 'assignedTo:id,name,email', 'createdBy:id,name,email']);
            return response()->json(['message' => 'Complaint converted to work order successfully.', 'work_order' => ['id' => $workOrder->id, 'work_order_number' => $workOrder->work_order_number, 'title' => $workOrder->title, 'description' => $workOrder->description, 'status' => $workOrder->status, 'priority' => $workOrder->priority, 'assigned_to' => $workOrder->assignedTo ? ['id' => $workOrder->assignedTo->id, 'name' => $workOrder->assignedTo->name, 'email' => $workOrder->assignedTo->email] : null, 'complaints' => $workOrder->complaints->map(fn ($item) => ['id' => $item->id, 'complaint_number' => $item->complaint_number, 'title' => $item->title, 'status' => $item->status])->values()]], 201);
        });
    }

    public function addToExistingWorkOrder(Request $request, Complaint $complaint): JsonResponse
    {
        abort_unless($request->user()->can('complaints.update') && $request->user()->can('tasks.update'), 403);
        $validated = $request->validate(['work_order_id' => ['required', 'exists:work_orders,id']]);
        return DB::transaction(function () use ($validated, $complaint, $request) {
            $workOrder = WorkOrder::query()->lockForUpdate()->findOrFail($validated['work_order_id']);
            if (in_array($workOrder->status, ['completed', 'cancelled'], true)) return response()->json(['message' => 'Cannot add a complaint to a completed or cancelled work order.'], 422);
            if (!$workOrder->complaints()->whereKey($complaint->id)->exists()) $workOrder->complaints()->attach($complaint->id);
            $complaint->update(['status' => 'in_progress', 'assigned_to' => $workOrder->assigned_to, 'processed_by' => $request->user()->id, 'processed_at' => now(), 'first_response_at' => $complaint->first_response_at ?? now()]);
            $workOrder->load(['complaints:id,complaint_number,title,status', 'assignedTo:id,name,email', 'createdBy:id,name,email']);
            return response()->json(['message' => 'Complaint added to work order successfully.', 'work_order_id' => $workOrder->id, 'work_order_number' => $workOrder->work_order_number, 'complaints' => $workOrder->complaints->map(fn ($item) => ['id' => $item->id, 'complaint_number' => $item->complaint_number, 'title' => $item->title, 'status' => $item->status])->values()]);
        });
    }

    private function generateComplaintNumber(): string
    {
        $nextNumber = DB::selectOne("SELECT nextval('complaints_number_seq') as next_number")->next_number;
        return 'CMP-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    private function validateUserActive(int $userId): void
    {
        $user = User::find($userId);
        if ($user && ! $user->is_active) abort(422, 'Cannot assign to inactive user.');
    }

    private function formatComplaint(Complaint $complaint, bool $withWorkOrders = false): array
    {
        $data = ['id' => $complaint->id, 'complaint_number' => $complaint->complaint_number, 'title' => $complaint->title, 'description' => $complaint->description, 'processing_notes' => $complaint->processing_notes, 'solution' => $complaint->solution, 'status' => $complaint->status, 'priority' => $complaint->priority, 'reported_by' => $complaint->reportedBy ? ['id' => $complaint->reportedBy->id, 'name' => $complaint->reportedBy->name, 'email' => $complaint->reportedBy->email] : null, 'assigned_to' => $complaint->assignedTo ? ['id' => $complaint->assignedTo->id, 'name' => $complaint->assignedTo->name, 'email' => $complaint->assignedTo->email] : null, 'processed_by' => $complaint->processedBy ? ['id' => $complaint->processedBy->id, 'name' => $complaint->processedBy->name, 'email' => $complaint->processedBy->email] : null, 'processed_at' => $complaint->processed_at?->toISOString(), 'first_response_at' => $complaint->first_response_at?->toISOString(), 'contact_name' => $complaint->contact_name, 'contact_phone' => $complaint->contact_phone, 'address' => $complaint->address, 'latitude' => $complaint->latitude ? (string) $complaint->latitude : null, 'longitude' => $complaint->longitude ? (string) $complaint->longitude : null, 'resolved_at' => $complaint->resolved_at?->toISOString(), 'created_at' => $complaint->created_at?->toISOString(), 'updated_at' => $complaint->updated_at?->toISOString()];
        if ($withWorkOrders && $complaint->relationLoaded('workOrders')) $data['work_orders'] = $complaint->workOrders->map(fn ($wo) => ['id' => $wo->id, 'work_order_number' => $wo->work_order_number, 'title' => $wo->title, 'status' => $wo->status, 'priority' => $wo->priority])->values();
        return $data;
    }
}
