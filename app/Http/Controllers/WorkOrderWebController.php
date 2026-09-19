<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Complaint;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkOrderWebController extends Controller
{
    public function index(Request $request): View
    {
        $query = WorkOrder::query()->with(['assignedTo:id,name', 'createdBy:id,name'])->withCount('complaints');
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', '');
        $priority = (string) $request->input('priority', '');
        $assignedTo = $request->input('assigned_to');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('work_order_number', 'ilike', "%{$search}%")
                    ->orWhere('title', 'ilike', "%{$search}%")
                    ->orWhereHas('assignedTo', fn ($u) => $u->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('complaints', fn ($c) => $c->where('complaint_number', 'ilike', "%{$search}%")->orWhere('title', 'ilike', "%{$search}%"));
            });
        }
        if (in_array($status, ['pending', 'assigned', 'in_progress', 'completed', 'cancelled'], true)) $query->where('status', $status); else $status = '';
        if (in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) $query->where('priority', $priority); else $priority = '';
        if ($assignedTo !== null && $assignedTo !== '' && ctype_digit((string) $assignedTo)) $query->where('assigned_to', (int) $assignedTo); else $assignedTo = '';

        $workOrders = $query->orderByDesc('created_at')->paginate(15)->withQueryString();
        $users = User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        return view('work-orders.index', compact('workOrders', 'users', 'search', 'status', 'priority', 'assignedTo'));
    }

    public function create(): View
    {
        return view('work-orders.create', ['users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string'], 'priority' => ['required', 'in:low,medium,high,urgent'], 'assigned_to' => ['nullable', 'exists:users,id'], 'notes' => ['nullable', 'string'], 'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180']]);
        if (array_key_exists('notes', $validated) && $validated['notes'] !== null) abort_unless($request->user()->can('tasks.update'), 403);
        if (array_key_exists('latitude', $validated) || array_key_exists('longitude', $validated)) abort_unless($request->user()->can('tasks.update'), 403);
        if (!empty($validated['assigned_to'])) { abort_unless($request->user()->can('tasks.assign'), 403); $this->ensureActiveUser($validated['assigned_to']); }

        $workOrder = DB::transaction(function () use ($validated, $request) {
            $next = DB::selectOne("SELECT nextval('work_orders_number_seq') AS next_number")->next_number;
            return WorkOrder::create(['work_order_number' => 'WO-' . str_pad($next, 6, '0', STR_PAD_LEFT), 'title' => $validated['title'], 'description' => $validated['description'], 'status' => $validated['assigned_to'] ? 'assigned' : 'pending', 'priority' => $validated['priority'], 'assigned_to' => $validated['assigned_to'] ?? null, 'created_by' => $request->user()->id, 'notes' => $validated['notes'] ?? null, 'latitude' => $validated['latitude'] ?? null, 'longitude' => $validated['longitude'] ?? null]);
        });
        return redirect()->route('work-orders.show', $workOrder)->with('success', 'تم إنشاء المهمة بنجاح.');
    }

    public function show(WorkOrder $workOrder): View
    {
        $workOrder->load(['assignedTo:id,name,email', 'createdBy:id,name,email', 'complaints' => fn ($q) => $q->with('assignedTo:id,name')->orderByDesc('created_at')]);
        return view('work-orders.show', ['workOrder' => $workOrder, 'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function convertToComplaint(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        abort_unless($request->user()->can('tasks.update') && $request->user()->can('complaints.create'), 403);
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated, $workOrder, $request) {
            $next = DB::selectOne("SELECT nextval('complaints_number_seq') AS next_number")->next_number;
            $complaint = Complaint::create([
                'complaint_number' => 'CMP-' . str_pad($next, 6, '0', STR_PAD_LEFT),
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
            if ($workOrder->status === 'completed') $complaint->update(['status' => 'closed', 'resolved_at' => now(), 'processed_by' => $request->user()->id, 'processed_at' => now()]);
            elseif (!in_array($workOrder->status, ['cancelled', 'pending'], true)) $complaint->update(['status' => 'in_progress', 'processed_by' => $request->user()->id, 'processed_at' => now()]);
        });

        return redirect()->route('work-orders.show', $workOrder)->with('success', 'تم تحويل المهمة إلى شكوى وربطها بها بنجاح.');
    }

    public function update(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $validated = $request->validate(['status' => ['sometimes', 'required', 'in:pending,assigned,in_progress,completed,cancelled'], 'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'], 'priority' => ['sometimes', 'required', 'in:low,medium,high,urgent'], 'notes' => ['sometimes', 'nullable', 'string'], 'title' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['sometimes', 'required', 'string'], 'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'], 'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180']]);
        abort_unless($validated !== [], 422);
        if (array_key_exists('status', $validated)) abort_unless($request->user()->can('tasks.transition'), 403);
        if (array_key_exists('assigned_to', $validated)) { abort_unless($request->user()->can('tasks.assign'), 403); if (!empty($validated['assigned_to'])) $this->ensureActiveUser($validated['assigned_to']); }
        foreach (['title', 'description', 'priority', 'notes', 'latitude', 'longitude'] as $field) if (array_key_exists($field, $validated)) abort_unless($request->user()->can('tasks.update'), 403);

        $old = $workOrder->status;
        $new = $validated['status'] ?? $old;
        $transitions = ['pending' => ['assigned', 'cancelled'], 'assigned' => ['in_progress', 'cancelled', 'pending'], 'in_progress' => ['completed', 'cancelled', 'assigned'], 'completed' => ['in_progress'], 'cancelled' => ['pending']];
        if ($old !== $new && !in_array($new, $transitions[$old] ?? [], true)) return back()->withErrors(['status' => 'انتقال حالة المهمة المطلوب غير مسموح به.'])->withInput();

        DB::transaction(function () use ($workOrder, $validated, $old, $new) {
            $changes = [];
            foreach (['title', 'description', 'priority', 'notes', 'assigned_to', 'status', 'latitude', 'longitude'] as $field) if (array_key_exists($field, $validated)) $changes[$field] = $validated[$field];
            $workOrder->update($changes);
            if ($new === 'in_progress' && !$workOrder->started_at) $workOrder->update(['started_at' => now()]);
            if ($new === 'completed' && !$workOrder->completed_at) {
                $workOrder->update(['completed_at' => now()]);
                $workOrder->load('complaints');
                foreach ($workOrder->complaints as $complaint) {
                    if ($complaint->status === 'cancelled') continue;
                    $hasIncompleteWorkOrders = $complaint->workOrders()->where('status', '<>', 'completed')->exists();
                    if (!$hasIncompleteWorkOrders) $complaint->update(['status' => 'closed', 'resolved_at' => $complaint->resolved_at ?? now(), 'processed_by' => auth()->id(), 'processed_at' => now()]);
                }
            }
        });

        return redirect()->route('work-orders.show', $workOrder)->with('success', 'تم تحديث المهمة بنجاح.');
    }

    public function destroy(WorkOrder $workOrder): RedirectResponse
    {
        if ($workOrder->complaints()->exists()) return back()->withErrors(['delete' => 'لا يمكن حذف مهمة مرتبطة بشكوى.']);
        $workOrder->delete();
        return redirect()->route('work-orders.index')->with('success', 'تم حذف المهمة بنجاح.');
    }

    private function ensureActiveUser(int $userId): void
    {
        $user = User::find($userId);
        abort_if(!$user || !$user->is_active, 422, 'لا يمكن إسناد المهمة إلى مستخدم غير نشط.');
    }
}
