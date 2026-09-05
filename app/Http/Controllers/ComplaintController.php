<?php

namespace App\Http\Controllers;

use App\Http\Requests\Complaint\StoreComplaintRequest;
use App\Http\Requests\Complaint\UpdateComplaintRequest;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ComplaintController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Complaint::with(['reportedBy:id,name,email', 'assignedTo:id,name,email']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->has('reported_by')) {
            $query->where('reported_by', $request->reported_by);
        }

        $complaints = $query->orderBy('created_at', 'desc')->paginate();

        $data = $complaints->getCollection()->map(function ($complaint) {
            return $this->formatComplaint($complaint);
        });

        return response()->json([
            'data' => $data,
            'links' => [
                'first' => $complaints->url(1),
                'last' => $complaints->url($complaints->lastPage()),
                'prev' => $complaints->previousPageUrl(),
                'next' => $complaints->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $complaints->currentPage(),
                'from' => $complaints->firstItem(),
                'last_page' => $complaints->lastPage(),
                'path' => $complaints->path(),
                'per_page' => $complaints->perPage(),
                'to' => $complaints->lastItem(),
                'total' => $complaints->total(),
            ],
        ]);
    }

    public function store(StoreComplaintRequest $request): JsonResponse
    {
        $validated = $request->validated();
        // Always use authenticated user as reported_by, ignore client-provided value
        $reportedBy = $request->user()->id;

        return DB::transaction(function () use ($validated, $reportedBy) {
            $complaintNumber = $this->generateComplaintNumber();

            $complaint = Complaint::create([
                'complaint_number' => $complaintNumber,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'status' => $validated['status'] ?? 'open',
                'priority' => $validated['priority'] ?? 'medium',
                'reported_by' => $reportedBy,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'contact_name' => $validated['contact_name'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ]);

            if ($complaint->assigned_to) {
                $this->validateUserActive($complaint->assigned_to);
            }

            $complaint->load(['reportedBy:id,name,email', 'assignedTo:id,name,email']);

            return response()->json($this->formatComplaint($complaint), 201);
        });
    }

    public function show(Complaint $complaint): JsonResponse
    {
        $complaint->load(['reportedBy:id,name,email', 'assignedTo:id,name,email', 'workOrders']);

        return response()->json($this->formatComplaint($complaint, true));
    }

    public function update(UpdateComplaintRequest $request, Complaint $complaint): JsonResponse
    {
        $validated = $request->validated();

        // Remove protected fields that cannot be updated by client
        unset(
            $validated['complaint_number'],
            $validated['reported_by'],
            $validated['created_at'],
            $validated['updated_at'],
            $validated['resolved_at']
        );

        $oldStatus = $complaint->status;

        return DB::transaction(function () use ($validated, $complaint, $oldStatus) {
            if (isset($validated['assigned_to'])) {
                $this->validateUserActive($validated['assigned_to']);
            }

            $complaint->update($validated);

            // Handle status transitions and timestamps
            if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
                $this->handleStatusTransition($complaint, $oldStatus, $validated['status']);
            }

            $complaint->load(['reportedBy:id,name,email', 'assignedTo:id,name,email']);

            return response()->json($this->formatComplaint($complaint));
        });
    }

    private function generateComplaintNumber(): string
    {
        $prefix = 'CMP-';
        $nextNumber = DB::selectOne('SELECT nextval(\'complaints_number_seq\') as next_number')->next_number;
        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    private function validateUserActive(int $userId): void
    {
        $user = User::find($userId);
        if ($user && !$user->is_active) {
            abort(422, 'Cannot assign to inactive user.');
        }
    }

    private function handleStatusTransition(Complaint $complaint, string $oldStatus, string $newStatus): void
    {
        $validTransitions = [
            'open' => ['in_progress', 'cancelled'],
            'in_progress' => ['resolved', 'cancelled', 'open'],
            'resolved' => ['closed', 'in_progress'],
            'closed' => [],
            'cancelled' => ['open'],
        ];

        if (isset($validTransitions[$oldStatus]) && !in_array($newStatus, $validTransitions[$oldStatus])) {
            abort(422, "Invalid status transition from {$oldStatus} to {$newStatus}.");
        }

        if ($newStatus === 'resolved' && !$complaint->resolved_at) {
            $complaint->update(['resolved_at' => now()]);
        }
    }

    private function formatComplaint(Complaint $complaint, bool $withWorkOrders = false): array
    {
        $data = [
            'id' => $complaint->id,
            'complaint_number' => $complaint->complaint_number,
            'title' => $complaint->title,
            'description' => $complaint->description,
            'status' => $complaint->status,
            'priority' => $complaint->priority,
            'reported_by' => $complaint->reportedBy ? [
                'id' => $complaint->reportedBy->id,
                'name' => $complaint->reportedBy->name,
                'email' => $complaint->reportedBy->email,
            ] : null,
            'assigned_to' => $complaint->assignedTo ? [
                'id' => $complaint->assignedTo->id,
                'name' => $complaint->assignedTo->name,
                'email' => $complaint->assignedTo->email,
            ] : null,
            'contact_name' => $complaint->contact_name,
            'contact_phone' => $complaint->contact_phone,
            'address' => $complaint->address,
            'latitude' => $complaint->latitude ? (string) $complaint->latitude : null,
            'longitude' => $complaint->longitude ? (string) $complaint->longitude : null,
            'resolved_at' => $complaint->resolved_at?->toISOString(),
            'created_at' => $complaint->created_at?->toISOString(),
            'updated_at' => $complaint->updated_at?->toISOString(),
        ];

        if ($withWorkOrders && $complaint->relationLoaded('workOrders')) {
            $data['work_orders'] = $complaint->workOrders->map(function ($wo) {
                return [
                    'id' => $wo->id,
                    'work_order_number' => $wo->work_order_number,
                    'title' => $wo->title,
                    'status' => $wo->status,
                    'priority' => $wo->priority,
                ];
            })->values();
        }

        return $data;
    }
}