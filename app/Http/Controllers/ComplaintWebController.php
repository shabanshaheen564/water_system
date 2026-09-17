<?php

namespace App\Http\Controllers;

use App\Http\Requests\Complaint\StoreComplaintRequest;
use App\Http\Requests\Complaint\UpdateComplaintRequest;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ComplaintWebController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search'));
        $query = Complaint::with(['reportedBy:id,name', 'assignedTo:id,name'])
            ->withCount('workOrders')
            ->latest();

        if ($search !== '') {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $search);
            $query->where(function ($q) use ($escaped) {
                $q->where('complaint_number', 'ILIKE', "%{$escaped}%")
                    ->orWhere('title', 'ILIKE', "%{$escaped}%")
                    ->orWhere('description', 'ILIKE', "%{$escaped}%")
                    ->orWhere('contact_name', 'ILIKE', "%{$escaped}%")
                    ->orWhere('contact_phone', 'ILIKE', "%{$escaped}%");
            });
        }

        foreach (['status', 'priority'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        $complaints = $query->paginate(20)->withQueryString();

        return view('complaints.index', [
            'complaints' => $complaints,
            'search' => $search,
            'status' => $request->input('status'),
            'priority' => $request->input('priority'),
        ]);
    }

    public function create(): View
    {
        return view('complaints.create', ['assignees' => $this->assignableUsers()]);
    }

    public function store(StoreComplaintRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $assignedTo = $validated['assigned_to'] ?? null;

        if ($assignedTo !== null) {
            $this->ensureActiveUser($assignedTo);
        }

        DB::transaction(function () use ($validated, $request) {
            $nextNumber = DB::selectOne("SELECT nextval('complaints_number_seq') AS next_number")->next_number;

            Complaint::create([
                'complaint_number' => 'CMP-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT),
                'title' => $validated['title'],
                'description' => $validated['description'],
                'status' => 'open',
                'priority' => $validated['priority'] ?? 'medium',
                'reported_by' => $request->user()->id,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'contact_name' => $validated['contact_name'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ]);
        });

        return redirect()->route('complaints.index')->with('success', 'تم تسجيل الشكوى بنجاح.');
    }

    public function show(Complaint $complaint): View
    {
        $complaint->load([
            'reportedBy:id,name,email',
            'assignedTo:id,name,email',
            'processedBy:id,name,email',
            'workOrders',
        ]);

        return view('complaints.show', compact('complaint'));
    }

    public function edit(Complaint $complaint): View
    {
        $complaint->load(['reportedBy:id,name', 'assignedTo:id,name', 'processedBy:id,name']);

        return view('complaints.edit', [
            'complaint' => $complaint,
            'assignees' => $this->assignableUsers(),
        ]);
    }

    public function update(UpdateComplaintRequest $request, Complaint $complaint): RedirectResponse
    {
        $validated = $request->validated();
        $oldStatus = $complaint->status;

        if (array_key_exists('assigned_to', $validated) && $validated['assigned_to'] !== null) {
            $this->ensureActiveUser($validated['assigned_to']);
        }

        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            $validTransitions = [
                'open' => ['in_progress', 'cancelled'],
                'in_progress' => ['resolved', 'cancelled', 'open'],
                'resolved' => ['closed', 'in_progress'],
                'closed' => [],
                'cancelled' => ['open'],
            ];

            if (! in_array($validated['status'], $validTransitions[$oldStatus] ?? [], true)) {
                return back()->withErrors(['status' => "لا يمكن تغيير الحالة من {$oldStatus} إلى {$validated['status']}."])->withInput();
            }
        }

        if (array_key_exists('processing_notes', $validated) || array_key_exists('solution', $validated)) {
            $validated['processed_by'] = $request->user()->id;
            $validated['processed_at'] = now();
        }

        if (($validated['status'] ?? null) === 'resolved' && ! $complaint->resolved_at) {
            $validated['resolved_at'] = now();
            $validated['processed_by'] ??= $request->user()->id;
            $validated['processed_at'] ??= now();
        }

        $complaint->update($validated);

        return redirect()->route('complaints.show', $complaint)->with('success', 'تم تحديث الشكوى بنجاح.');
    }

    public function destroy(Complaint $complaint): RedirectResponse
    {
        if ($complaint->workOrders()->exists()) {
            return back()->withErrors(['delete' => 'لا يمكن حذف شكوى مرتبطة بمهمة.']);
        }

        $complaint->delete();

        return redirect()->route('complaints.index')->with('success', 'تم حذف الشكوى بنجاح.');
    }

    private function assignableUsers()
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function ensureActiveUser(int $userId): void
    {
        $user = User::find($userId);

        if (! $user || ! $user->is_active) {
            abort(422, 'لا يمكن إسناد الشكوى إلى مستخدم غير نشط.');
        }
    }
}
