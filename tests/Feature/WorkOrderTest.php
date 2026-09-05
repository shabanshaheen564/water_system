<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $adminToken;
    protected User $user;
    protected string $userToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);

        $this->admin = User::factory()->create();
        $permission = Permission::where('name', 'tasks.create')->first();
        $this->admin->givePermissionTo($permission);
        $permission = Permission::where('name', 'tasks.view')->first();
        $this->admin->givePermissionTo($permission);
        $permission = Permission::where('name', 'tasks.update')->first();
        $this->admin->givePermissionTo($permission);
        $permission = Permission::where('name', 'complaints.view')->first();
        $this->admin->givePermissionTo($permission);

        $this->adminToken = $this->admin->createToken('mobile-app')->plainTextToken;

        $this->user = User::factory()->create();
        $permission = Permission::where('name', 'tasks.view')->first();
        $this->user->givePermissionTo($permission);
        $this->userToken = $this->user->createToken('mobile-app')->plainTextToken;
    }

    // Authentication & Authorization Tests
    public function test_unauthenticated_user_cannot_list_work_orders(): void
    {
        $response = $this->getJson('/api/work-orders');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_create_work_order(): void
    {
        $response = $this->postJson('/api/work-orders', [
            'title' => 'Test Work Order',
            'description' => 'Test Description',
        ]);
        $response->assertStatus(401);
    }

    public function test_user_without_view_permission_gets_403_on_list(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/work-orders');

        $response->assertStatus(403);
    }

    public function test_user_without_create_permission_gets_403_on_create(): void
    {
        $user = User::factory()->create();
        $permission = Permission::where('name', 'tasks.view')->first();
        $user->givePermissionTo($permission);
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/work-orders', [
            'title' => 'Test Work Order',
            'description' => 'Test Description',
        ]);

        $response->assertStatus(403);
    }

    // CRUD Tests
    public function test_user_with_create_permission_can_create_work_order(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test Complaint',
            'description' => 'Test Description',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/work-orders', [
            'complaint_id' => $complaint->id,
            'title' => 'Fix Water Leak',
            'description' => 'Repair leak on Main St',
            'priority' => 'high',
            'assigned_to' => $this->admin->id,
            'notes' => 'Urgent repair needed',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'work_order_number',
                'complaint',
                'title',
                'description',
                'status',
                'priority',
                'assigned_to',
                'created_by',
                'started_at',
                'completed_at',
                'notes',
                'created_at',
                'updated_at',
            ]);

        $this->assertEquals('Fix Water Leak', $response->json('title'));
        $this->assertEquals('Repair leak on Main St', $response->json('description'));
        $this->assertEquals('high', $response->json('priority'));
        $this->assertEquals('pending', $response->json('status'));
        $this->assertNotNull($response->json('work_order_number'));
        $this->assertStringStartsWith('WO-', $response->json('work_order_number'));
        $this->assertEquals($this->admin->id, $response->json('created_by.id'));
        $this->assertEquals($complaint->id, $response->json('complaint.id'));
        $this->assertNull($response->json('started_at'));
        $this->assertNull($response->json('completed_at'));

        $this->assertDatabaseHas('work_orders', [
            'title' => 'Fix Water Leak',
            'description' => 'Repair leak on Main St',
        ]);
    }

    public function test_create_work_order_generates_unique_number(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test Complaint',
            'description' => 'Test Description',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/work-orders', [
            'title' => 'Work Order 1',
            'description' => 'Description 1',
            'complaint_id' => $complaint->id,
        ]);

        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/work-orders', [
            'title' => 'Work Order 2',
            'description' => 'Description 2',
            'complaint_id' => $complaint->id,
        ]);

        $response1->assertStatus(201);
        $response2->assertStatus(201);

        $this->assertNotEquals($response1->json('work_order_number'), $response2->json('work_order_number'));
    }

    public function test_can_list_work_orders_with_pagination(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test Complaint',
            'description' => 'Test Description',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $this->admin->createdWorkOrders()->create([
                'work_order_number' => 'WO-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'complaint_id' => $complaint->id,
                'title' => "Work Order $i",
                'description' => "Description $i",
                'status' => 'pending',
                'priority' => 'medium',
                'created_by' => $this->admin->id,
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/work-orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_can_filter_work_orders_by_status(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test Complaint',
            'description' => 'Test Description',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Pending Work Order',
            'description' => 'Description',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);
        $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000002',
            'complaint_id' => $complaint->id,
            'title' => 'Completed Work Order',
            'description' => 'Description',
            'status' => 'completed',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/work-orders?status=completed');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('completed', $response->json('data.0.status'));
    }

    public function test_can_filter_work_orders_by_priority(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test Complaint',
            'description' => 'Test Description',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Low Priority',
            'description' => 'Description',
            'status' => 'pending',
            'priority' => 'low',
            'created_by' => $this->admin->id,
        ]);
        $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000002',
            'complaint_id' => $complaint->id,
            'title' => 'Urgent Priority',
            'description' => 'Description',
            'status' => 'pending',
            'priority' => 'urgent',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/work-orders?priority=urgent');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('urgent', $response->json('data.0.priority'));
    }

    public function test_can_filter_work_orders_by_assigned_to(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test Complaint',
            'description' => 'Test Description',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $assignee = User::factory()->create();
        $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Assigned Work Order',
            'description' => 'Description',
            'status' => 'assigned',
            'priority' => 'medium',
            'assigned_to' => $assignee->id,
            'created_by' => $this->admin->id,
        ]);
        $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000002',
            'complaint_id' => $complaint->id,
            'title' => 'Unassigned Work Order',
            'description' => 'Description',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/work-orders?assigned_to=' . $assignee->id);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($assignee->id, $response->json('data.0.assigned_to.id'));
    }

    public function test_can_filter_work_orders_by_complaint_id(): void
    {
        $complaint1 = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Complaint 1',
            'description' => 'Description',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $complaint2 = Complaint::create([
            'complaint_number' => 'CMP-000002',
            'title' => 'Complaint 2',
            'description' => 'Description',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint1->id,
            'title' => 'Work Order for Complaint 1',
            'description' => 'Description',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);
        $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000002',
            'complaint_id' => $complaint2->id,
            'title' => 'Work Order for Complaint 2',
            'description' => 'Description',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/work-orders?complaint_id=' . $complaint1->id);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($complaint1->id, $response->json('data.0.complaint.id'));
    }

    public function test_can_show_work_order(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test Complaint',
            'description' => 'Test Description',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $workOrder = $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Test Work Order',
            'description' => 'Test Description',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/work-orders/{$workOrder->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'work_order_number',
                'complaint',
                'title',
                'description',
                'status',
                'priority',
                'assigned_to',
                'created_by',
                'started_at',
                'completed_at',
                'notes',
                'created_at',
                'updated_at',
            ]);

        $this->assertEquals('Test Work Order', $response->json('title'));
    }

    public function test_show_work_order_returns_404_for_nonexistent(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/work-orders/999999');

        $response->assertStatus(404);
    }

    public function test_user_with_update_permission_can_update_work_order(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test Complaint',
            'description' => 'Test Description',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $workOrder = $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Original Title',
            'description' => 'Original Description',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        $assignee = User::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/work-orders/{$workOrder->id}", [
            'title' => 'Updated Title',
            'description' => 'Updated Description',
            'priority' => 'high',
            'assigned_to' => $assignee->id,
            'notes' => 'Updated notes',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Title', $response->json('title'));
        $this->assertEquals('Updated Description', $response->json('description'));
        $this->assertEquals('high', $response->json('priority'));
        $this->assertEquals($assignee->id, $response->json('assigned_to.id'));
        $this->assertEquals('Updated notes', $response->json('notes'));
    }

    public function test_cannot_update_work_order_number(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $workOrder = $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/work-orders/{$workOrder->id}", [
            'work_order_number' => 'WO-999999',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('WO-000001', $response->json('work_order_number'));
    }

    public function test_cannot_update_created_by(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $workOrder = $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        $otherUser = User::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/work-orders/{$workOrder->id}", [
            'created_by' => $otherUser->id,
        ]);

        $response->assertStatus(200);
        $this->assertEquals($this->admin->id, $response->json('created_by.id'));
    }

    public function test_cannot_update_started_at(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $workOrder = $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/work-orders/{$workOrder->id}", [
            'started_at' => '2024-01-01 00:00:00',
        ]);

        $response->assertStatus(200);
        $this->assertNull($response->json('started_at'));
    }

    public function test_cannot_update_completed_at(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $workOrder = $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/work-orders/{$workOrder->id}", [
            'completed_at' => '2024-01-01 00:00:00',
        ]);

        $response->assertStatus(200);
        $this->assertNull($response->json('completed_at'));
    }

    // Validation Tests
    public function test_validation_errors_get_422(): void
    {
        // Missing title
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/work-orders', [
            'description' => 'Test Description',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);

        // Invalid status
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/work-orders', [
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'invalid_status',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // Invalid priority
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/work-orders', [
            'title' => 'Test',
            'description' => 'Test',
            'priority' => 'invalid_priority',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    }

    // Status Transition Tests
    public function test_valid_status_transitions(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $workOrder = $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        // pending -> assigned
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/work-orders/{$workOrder->id}", ['status' => 'assigned']);
        $response->assertStatus(200);
        $this->assertEquals('assigned', $response->json('status'));

        // assigned -> in_progress
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/work-orders/{$workOrder->id}", ['status' => 'in_progress']);
        $response->assertStatus(200);
        $this->assertEquals('in_progress', $response->json('status'));
        $this->assertNotNull($response->json('started_at'));

        // in_progress -> completed
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/work-orders/{$workOrder->id}", ['status' => 'completed']);
        $response->assertStatus(200);
        $this->assertEquals('completed', $response->json('status'));
        $this->assertNotNull($response->json('completed_at'));
    }

    public function test_invalid_status_transition_gets_422(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $workOrder = $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        // pending -> completed (invalid, must go through assigned and in_progress)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/work-orders/{$workOrder->id}", ['status' => 'completed']);
        $response->assertStatus(422);
    }

    public function test_cancelled_work_order_can_be_reopened(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $workOrder = $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'cancelled',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/work-orders/{$workOrder->id}", ['status' => 'pending']);
        $response->assertStatus(200);
        $this->assertEquals('pending', $response->json('status'));
    }

    // Inactive User Assignment Tests
    public function test_cannot_assign_work_order_to_inactive_user(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $inactiveUser = User::factory()->create(['is_active' => false]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/work-orders', [
            'title' => 'Test',
            'description' => 'Test',
            'complaint_id' => $complaint->id,
            'assigned_to' => $inactiveUser->id,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('inactive', $response->json('message'));
    }

    public function test_cannot_update_assigned_to_inactive_user(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $workOrder = $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        $inactiveUser = User::factory()->create(['is_active' => false]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/work-orders/{$workOrder->id}", [
            'assigned_to' => $inactiveUser->id,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('inactive', $response->json('message'));
    }

    public function test_can_assign_work_order_to_active_user(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $activeUser = User::factory()->create(['is_active' => true]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/work-orders', [
            'title' => 'Test',
            'description' => 'Test',
            'complaint_id' => $complaint->id,
            'assigned_to' => $activeUser->id,
        ]);

        $response->assertStatus(201);
        $this->assertEquals($activeUser->id, $response->json('assigned_to.id'));
    }

    // Timestamp Tests
    public function test_started_at_set_when_status_changes_to_in_progress(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $workOrder = $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'assigned',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/work-orders/{$workOrder->id}", ['status' => 'in_progress']);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('started_at'));
    }

    public function test_completed_at_set_when_status_changes_to_completed(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $workOrder = $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'in_progress',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/work-orders/{$workOrder->id}", ['status' => 'completed']);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('completed_at'));
    }

    public function test_started_at_not_changed_when_status_not_in_progress(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $workOrder = $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/work-orders/{$workOrder->id}", ['status' => 'assigned']);

        $response->assertStatus(200);
        $this->assertNull($response->json('started_at'));
    }

    public function test_create_work_order_with_in_progress_sets_started_at(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/work-orders', [
            'title' => 'Test',
            'description' => 'Test',
            'complaint_id' => $complaint->id,
            'status' => 'in_progress',
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('started_at'));
    }

    // Relationship Tests
    public function test_work_order_returns_relationships(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test Complaint',
            'description' => 'Test Description',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $assignee = User::factory()->create();
        $workOrder = $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'pending',
            'priority' => 'medium',
            'assigned_to' => $assignee->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/work-orders/{$workOrder->id}");

        $response->assertStatus(200);
        $this->assertEquals($complaint->id, $response->json('complaint.id'));
        $this->assertEquals($assignee->id, $response->json('assigned_to.id'));
        $this->assertEquals($this->admin->id, $response->json('created_by.id'));
        $this->assertArrayHasKey('title', $response->json('complaint'));
        $this->assertArrayHasKey('name', $response->json('assigned_to'));
        $this->assertArrayHasKey('name', $response->json('created_by'));
    }

    // Sensitive Fields Tests
    public function test_sensitive_fields_not_returned(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $workOrder = $this->admin->createdWorkOrders()->create([
            'work_order_number' => 'WO-000001',
            'complaint_id' => $complaint->id,
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/work-orders/{$workOrder->id}");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
    }

    // Mass Assignment Protection Tests
    public function test_mass_assignment_protection_on_create(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/work-orders', [
            'title' => 'Test',
            'description' => 'Test',
            'complaint_id' => $complaint->id,
            'work_order_number' => 'WO-999999',
            'created_by' => 999,
            'started_at' => '2024-01-01 00:00:00',
            'completed_at' => '2024-01-01 00:00:00',
        ]);

        $response->assertStatus(201);
        $this->assertNotEquals('WO-999999', $response->json('work_order_number'));
        $this->assertEquals($this->admin->id, $response->json('created_by.id'));
        $this->assertNull($response->json('started_at'));
        $this->assertNull($response->json('completed_at'));
    }

    // Work Order - Complaint Relationship
    public function test_work_order_links_to_complaint(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test Complaint',
            'description' => 'Test Description',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/work-orders', [
            'title' => 'Work Order',
            'description' => 'Description',
            'complaint_id' => $complaint->id,
        ]);

        $response->assertStatus(201);
        $this->assertEquals($complaint->id, $response->json('complaint.id'));
        $this->assertEquals('CMP-000001', $response->json('complaint.complaint_number'));
    }

    public function test_work_order_can_be_created_without_complaint(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/work-orders', [
            'title' => 'Standalone Work Order',
            'description' => 'Description',
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('complaint'));
    }

    public function test_concurrent_work_order_creation_generates_unique_numbers(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test Complaint',
            'description' => 'Test Description',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->admin->id,
        ]);

        $numbers = [];
        for ($i = 0; $i < 10; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->adminToken,
            ])->postJson('/api/work-orders', [
                'title' => "Concurrent Work Order $i",
                'description' => "Description $i",
                'complaint_id' => $complaint->id,
            ]);
            $response->assertStatus(201);
            $numbers[] = $response->json('work_order_number');
        }

        // All numbers should be unique
        $this->assertCount(10, array_unique($numbers));

        // Numbers should be sequential
        sort($numbers);
        for ($i = 1; $i < count($numbers); $i++) {
            $prev = (int) Str::after($numbers[$i - 1], 'WO-');
            $curr = (int) Str::after($numbers[$i], 'WO-');
            $this->assertEquals($prev + 1, $curr, 'Work order numbers should be sequential');
        }
    }
}