<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkOrderWebController extends Controller
{
    public function index(Request $request): View
    {
        $query = WorkOrder::query()
            ->with(['assignedTo:id,name', 'createdBy:id,name'])
            ->withCount('complaints');

        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', '');
        $priority = (string) $request->input('priority', '');
        $assignedTo = $request->input('assigned_to');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('work_order_number', 'ilike', "%{$search}%")
                    ->orWhere('title', 'ilike', "%{$search}%")
                    ->orWhereHas('assignedTo', fn ($user) => $user->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('complaints', function ($complaint) use ($search) {
                        $complaint->where('complaint_number', 'ilike', "%{$search}%")
                            ->orWhere('title', 'ilike', "%{$search}%");
                    });
            });
        }

        if (in_array($status, ['pending', 'assigned', 'in_progress', 'completed', 'cancelled'], true)) {
            $query->where('status', $status);
        } else {
            $status = '';
        }

        if (in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $query->where('priority', $priority);
        } else {
            $priority = '';
        }

        if ($assignedTo !== null && $assignedTo !== '' && ctype_digit((string) $assignedTo)) {
            $query->where('assigned_to', (int) $assignedTo);
        } else {
            $assignedTo = '';
        }

        $workOrders = $query->orderByDesc('created_at')->paginate(15)->withQueryString();
        $users = User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('work-orders.index', compact('workOrders', 'users', 'search', 'status', 'priority', 'assignedTo'));
    }

    public function show(WorkOrder $workOrder): View
    {
        $workOrder->load([
            'assignedTo:id,name,email',
            'createdBy:id,name,email',
            'complaints' => fn ($query) => $query->with('assignedTo:id,name')->orderByDesc('created_at'),
        ]);

        $users = User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('work-orders.show', compact('workOrder', 'users'));
    }

    public function update(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,assigned,in_progress,completed,cancelled'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'notes' => ['nullable', 'string'],
        ]);

        if (!empty($validated['assigned_to'])) {
            $user = User::findOrFail($validated['assigned_to']);
            abort_if(!$user->is_active, 422, 'لا يمكن إسناد المهمة إلى مستخدم غير نشط.');
        }

        $oldStatus = $workOrder->status;
        $newStatus = $validated['status'];
        $validTransitions = [
            'pending' => ['assigned', 'cancelled'],
            'assigned' => ['in_progress', 'cancelled', 'pending'],
            'in_progress' => ['completed', 'cancelled', 'assigned'],
            'completed' => ['in_progress'],
            'cancelled' => ['pending'],
        ];

        if ($oldStatus !== $newStatus && isset($validTransitions[$oldStatus]) && !in_array($newStatus, $validTransitions[$oldStatus], true)) {
            return back()->withErrors(['status' => 'انتقال حالة المهمة المطلوب غير مسموح به.'])->withInput();
        }

        DB::transaction(function () use ($workOrder, $validated, $oldStatus, $newStatus) {
            $workOrder->update([
                'status' => $newStatus,
                'priority' => $validated['priority'],
                'assigned_to' => $validated['assigned_to'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($newStatus === 'in_progress' && !$workOrder->started_at) {
                $workOrder->update(['started_at' => now()]);
            }

            if ($newStatus === 'completed' && !$workOrder->completed_at) {
                $workOrder->update(['completed_at' => now()]);
            }

            if ($oldStatus !== 'completed' && $newStatus === 'completed') {
                $workOrder->load('complaints');
                $workOrder->complaints()->update([
                    'status' => 'closed',
                    'resolved_at' => now(),
                    'processed_by' => auth()->id(),
                    'processed_at' => now(),
                ]);
            }
        });

        return redirect()->route('work-orders.show', $workOrder)->with('success', 'تم تحديث المهمة بنجاح.');
    }
}
