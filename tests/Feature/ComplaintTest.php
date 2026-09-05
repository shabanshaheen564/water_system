<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComplaintTest extends TestCase
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
        $permission = Permission::where('name', 'complaints.create')->first();
        $this->admin->givePermissionTo($permission);
        $permission = Permission::where('name', 'complaints.view')->first();
        $this->admin->givePermissionTo($permission);
        $permission = Permission::where('name', 'complaints.update')->first();
        $this->admin->givePermissionTo($permission);

        $this->adminToken = $this->admin->createToken('mobile-app')->plainTextToken;

        $this->user = User::factory()->create();
        $permission = Permission::where('name', 'complaints.view')->first();
        $this->user->givePermissionTo($permission);
        $this->userToken = $this->user->createToken('mobile-app')->plainTextToken;
    }

    // Authentication & Authorization Tests
    public function test_unauthenticated_user_cannot_list_complaints(): void
    {
        $response = $this->getJson('/api/complaints');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_create_complaint(): void
    {
        $response = $this->postJson('/api/complaints', [
            'title' => 'Test Complaint',
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
        ])->getJson('/api/complaints');

        $response->assertStatus(403);
    }

    public function test_user_without_create_permission_gets_403_on_create(): void
    {
        $user = User::factory()->create();
        $permission = Permission::where('name', 'complaints.view')->first();
        $user->givePermissionTo($permission);
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/complaints', [
            'title' => 'Test Complaint',
            'description' => 'Test Description',
        ]);

        $response->assertStatus(403);
    }

    // CRUD Tests
    public function test_user_with_create_permission_can_create_complaint(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/complaints', [
            'title' => 'Water Leak',
            'description' => 'Leak near main street',
            'priority' => 'high',
            'contact_name' => 'John Doe',
            'contact_phone' => '+1234567890',
            'address' => '123 Main St',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'complaint_number',
                'title',
                'description',
                'status',
                'priority',
                'reported_by',
                'assigned_to',
                'contact_name',
                'contact_phone',
                'address',
                'latitude',
                'longitude',
                'resolved_at',
                'created_at',
                'updated_at',
            ]);

        $this->assertEquals('Water Leak', $response->json('title'));
        $this->assertEquals('Leak near main street', $response->json('description'));
        $this->assertEquals('high', $response->json('priority'));
        $this->assertEquals('open', $response->json('status'));
        $this->assertNotNull($response->json('complaint_number'));
        $this->assertStringStartsWith('CMP-', $response->json('complaint_number'));
        $this->assertEquals($this->admin->id, $response->json('reported_by.id'));
        $this->assertNull($response->json('assigned_to'));
        $this->assertNull($response->json('resolved_at'));

        $this->assertDatabaseHas('complaints', [
            'title' => 'Water Leak',
            'description' => 'Leak near main street',
        ]);
    }

    public function test_create_complaint_generates_unique_number(): void
    {
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/complaints', [
            'title' => 'Complaint 1',
            'description' => 'Description 1',
        ]);

        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/complaints', [
            'title' => 'Complaint 2',
            'description' => 'Description 2',
        ]);

        $response1->assertStatus(201);
        $response2->assertStatus(201);

        $this->assertNotEquals($response1->json('complaint_number'), $response2->json('complaint_number'));
    }

    public function test_can_list_complaints_with_pagination(): void
    {
        // Create multiple complaints
        for ($i = 1; $i <= 5; $i++) {
            $this->admin->reportedComplaints()->create([
                'complaint_number' => 'CMP-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'title' => "Complaint $i",
                'description' => "Description $i",
                'status' => 'open',
                'priority' => 'medium',
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/complaints');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_can_filter_complaints_by_status(): void
    {
        $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Open Complaint',
            'description' => 'Description',
            'status' => 'open',
            'priority' => 'medium',
        ]);
        $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000002',
            'title' => 'Resolved Complaint',
            'description' => 'Description',
            'status' => 'resolved',
            'priority' => 'medium',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/complaints?status=resolved');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('resolved', $response->json('data.0.status'));
    }

    public function test_can_filter_complaints_by_priority(): void
    {
        $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Low Priority',
            'description' => 'Description',
            'status' => 'open',
            'priority' => 'low',
        ]);
        $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000002',
            'title' => 'Urgent Priority',
            'description' => 'Description',
            'status' => 'open',
            'priority' => 'urgent',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/complaints?priority=urgent');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('urgent', $response->json('data.0.priority'));
    }

    public function test_can_filter_complaints_by_assigned_to(): void
    {
        $assignee = User::factory()->create();
        $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Assigned Complaint',
            'description' => 'Description',
            'status' => 'open',
            'priority' => 'medium',
            'assigned_to' => $assignee->id,
        ]);
        $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000002',
            'title' => 'Unassigned Complaint',
            'description' => 'Description',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/complaints?assigned_to=' . $assignee->id);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($assignee->id, $response->json('data.0.assigned_to.id'));
    }

    public function test_can_filter_complaints_by_reported_by(): void
    {
        $otherUser = User::factory()->create();
        $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000001',
            'title' => 'My Complaint',
            'description' => 'Description',
            'status' => 'open',
            'priority' => 'medium',
        ]);
        $otherUser->reportedComplaints()->create([
            'complaint_number' => 'CMP-000002',
            'title' => 'Other Complaint',
            'description' => 'Description',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/complaints?reported_by=' . $this->admin->id);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->admin->id, $response->json('data.0.reported_by.id'));
    }

    public function test_can_show_complaint(): void
    {
        $complaint = $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test Complaint',
            'description' => 'Test Description',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/complaints/{$complaint->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'complaint_number',
                'title',
                'description',
                'status',
                'priority',
                'reported_by',
                'assigned_to',
                'contact_name',
                'contact_phone',
                'address',
                'latitude',
                'longitude',
                'resolved_at',
                'created_at',
                'updated_at',
                'work_orders',
            ]);

        $this->assertEquals('Test Complaint', $response->json('title'));
    }

    public function test_show_complaint_returns_404_for_nonexistent(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/complaints/999999');

        $response->assertStatus(404);
    }

    public function test_user_with_update_permission_can_update_complaint(): void
    {
        $complaint = $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Original Title',
            'description' => 'Original Description',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $assignee = User::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/complaints/{$complaint->id}", [
            'title' => 'Updated Title',
            'description' => 'Updated Description',
            'priority' => 'high',
            'assigned_to' => $assignee->id,
            'contact_name' => 'Jane Doe',
            'contact_phone' => '+0987654321',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Title', $response->json('title'));
        $this->assertEquals('Updated Description', $response->json('description'));
        $this->assertEquals('high', $response->json('priority'));
        $this->assertEquals($assignee->id, $response->json('assigned_to.id'));
        $this->assertEquals('Jane Doe', $response->json('contact_name'));
    }

    public function test_cannot_update_complaint_number(): void
    {
        $complaint = $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/complaints/{$complaint->id}", [
            'complaint_number' => 'CMP-999999',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('CMP-000001', $response->json('complaint_number'));
    }

    public function test_cannot_update_reported_by(): void
    {
        $complaint = $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $otherUser = User::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/complaints/{$complaint->id}", [
            'reported_by' => $otherUser->id,
        ]);

        $response->assertStatus(200);
        $this->assertEquals($this->admin->id, $response->json('reported_by.id'));
    }

    // Validation Tests
    public function test_validation_errors_get_422(): void
    {
        // Missing title
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/complaints', [
            'description' => 'Test Description',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);

        // Invalid status
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/complaints', [
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'invalid_status',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // Invalid priority
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/complaints', [
            'title' => 'Test',
            'description' => 'Test',
            'priority' => 'invalid_priority',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);

        // Invalid latitude
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/complaints', [
            'title' => 'Test',
            'description' => 'Test',
            'latitude' => 200,
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude']);

        // Invalid longitude
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/complaints', [
            'title' => 'Test',
            'description' => 'Test',
            'longitude' => -200,
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['longitude']);
    }

    // Status Transition Tests
    public function test_valid_status_transitions(): void
    {
        $complaint = $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        // open -> in_progress
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/complaints/{$complaint->id}", ['status' => 'in_progress']);
        $response->assertStatus(200);
        $this->assertEquals('in_progress', $response->json('status'));

        // in_progress -> resolved
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/complaints/{$complaint->id}", ['status' => 'resolved']);
        $response->assertStatus(200);
        $this->assertEquals('resolved', $response->json('status'));
        $this->assertNotNull($response->json('resolved_at'));

        // resolved -> closed
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/complaints/{$complaint->id}", ['status' => 'closed']);
        $response->assertStatus(200);
        $this->assertEquals('closed', $response->json('status'));
    }

    public function test_invalid_status_transition_gets_422(): void
    {
        $complaint = $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        // open -> resolved (invalid, must go through in_progress)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/complaints/{$complaint->id}", ['status' => 'resolved']);
        $response->assertStatus(422);
    }

    public function test_cancelled_complaint_can_be_reopened(): void
    {
        $complaint = $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $complaint->update(['status' => 'cancelled']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/complaints/{$complaint->id}", ['status' => 'open']);
        $response->assertStatus(200);
        $this->assertEquals('open', $response->json('status'));
    }

    // Inactive User Assignment Tests
    public function test_cannot_assign_to_inactive_user(): void
    {
        $inactiveUser = User::factory()->create(['is_active' => false]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/complaints', [
            'title' => 'Test',
            'description' => 'Test',
            'assigned_to' => $inactiveUser->id,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('inactive', $response->json('message'));
    }

    public function test_cannot_update_assigned_to_inactive_user(): void
    {
        $complaint = $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $inactiveUser = User::factory()->create(['is_active' => false]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/complaints/{$complaint->id}", [
            'assigned_to' => $inactiveUser->id,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('inactive', $response->json('message'));
    }

    public function test_can_assign_to_active_user(): void
    {
        $activeUser = User::factory()->create(['is_active' => true]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/complaints', [
            'title' => 'Test',
            'description' => 'Test',
            'assigned_to' => $activeUser->id,
        ]);

        $response->assertStatus(201);
        $this->assertEquals($activeUser->id, $response->json('assigned_to.id'));
    }

    // Timestamp Tests
    public function test_resolved_at_set_when_status_changes_to_resolved(): void
    {
        $complaint = $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'in_progress',
            'priority' => 'medium',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/complaints/{$complaint->id}", ['status' => 'resolved']);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('resolved_at'));
    }

    public function test_resolved_at_not_changed_when_status_not_resolved(): void
    {
        $complaint = $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/complaints/{$complaint->id}", ['status' => 'in_progress']);

        $response->assertStatus(200);
        $this->assertNull($response->json('resolved_at'));
    }

    // Relationship Tests
    public function test_complaint_returns_relationships(): void
    {
        $assignee = User::factory()->create();
        $complaint = $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
            'assigned_to' => $assignee->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/complaints/{$complaint->id}");

        $response->assertStatus(200);
        $this->assertEquals($this->admin->id, $response->json('reported_by.id'));
        $this->assertEquals($assignee->id, $response->json('assigned_to.id'));
        $this->assertArrayHasKey('name', $response->json('reported_by'));
        $this->assertArrayHasKey('email', $response->json('reported_by'));
        $this->assertArrayHasKey('name', $response->json('assigned_to'));
        $this->assertArrayHasKey('email', $response->json('assigned_to'));
    }

    // Sensitive Fields Tests
    public function test_sensitive_fields_not_returned(): void
    {
        $complaint = $this->admin->reportedComplaints()->create([
            'complaint_number' => 'CMP-000001',
            'title' => 'Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/complaints/{$complaint->id}");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
    }

    // Mass Assignment Protection Tests
    public function test_mass_assignment_protection_on_create(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/complaints', [
            'title' => 'Test',
            'description' => 'Test',
            'complaint_number' => 'CMP-999999',
            'reported_by' => $this->user->id, // valid user but should be ignored
            'resolved_at' => '2024-01-01 00:00:00',
        ]);

        $response->assertStatus(201);
        $this->assertNotEquals('CMP-999999', $response->json('complaint_number'));
        $this->assertEquals($this->admin->id, $response->json('reported_by.id'));
        $this->assertNull($response->json('resolved_at'));
    }

    public function test_concurrent_complaint_creation_generates_unique_numbers(): void
    {
        $numbers = [];
        for ($i = 0; $i < 10; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->adminToken,
            ])->postJson('/api/complaints', [
                'title' => "Concurrent Complaint $i",
                'description' => "Description $i",
            ]);
            $response->assertStatus(201);
            $numbers[] = $response->json('complaint_number');
        }

        // All numbers should be unique
        $this->assertCount(10, array_unique($numbers));

        // Numbers should be sequential (accounting for test isolation)
        sort($numbers);
        for ($i = 1; $i < count($numbers); $i++) {
            $prev = (int) Str::after($numbers[$i - 1], 'CMP-');
            $curr = (int) Str::after($numbers[$i], 'CMP-');
            $this->assertEquals($prev + 1, $curr, 'Complaint numbers should be sequential');
        }
    }
}