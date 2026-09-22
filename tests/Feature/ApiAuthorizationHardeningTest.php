<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ApiAuthorizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    }

    public function test_work_order_assignment_requires_tasks_assign_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName('tasks.update', 'web'));
        $user->givePermissionTo(Permission::findByName('tasks.transition', 'web'));
        $target = User::factory()->create();
        $workOrder = WorkOrder::create(['work_order_number' => 'WO-920001', 'title' => 'مهمة', 'description' => 'اختبار', 'status' => 'pending', 'priority' => 'medium', 'created_by' => $user->id]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->putJson("/api/work-orders/{$workOrder->id}", ['assigned_to' => $target->id])->assertForbidden();
    }

    public function test_work_order_status_change_requires_tasks_transition_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName('tasks.update', 'web'));
        $workOrder = WorkOrder::create(['work_order_number' => 'WO-920002', 'title' => 'مهمة', 'description' => 'اختبار', 'status' => 'pending', 'priority' => 'medium', 'created_by' => $user->id]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->putJson("/api/work-orders/{$workOrder->id}", ['status' => 'cancelled'])->assertForbidden();
    }

    public function test_work_order_update_allows_status_with_transition_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName('tasks.transition', 'web'));
        $workOrder = WorkOrder::create(['work_order_number' => 'WO-920003', 'title' => 'مهمة', 'description' => 'اختبار', 'status' => 'pending', 'priority' => 'medium', 'created_by' => $user->id]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->putJson("/api/work-orders/{$workOrder->id}", ['status' => 'cancelled'])->assertOk();
        $this->assertDatabaseHas('work_orders', ['id' => $workOrder->id, 'status' => 'cancelled']);
    }

    public function test_complaint_status_change_requires_complaints_transition_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName('complaints.update', 'web'));
        $complaint = Complaint::create(['complaint_number' => 'CMP-920001', 'title' => 'شكوى', 'description' => 'اختبار', 'status' => 'open', 'priority' => 'medium', 'reported_by' => $user->id]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->putJson("/api/complaints/{$complaint->id}", ['status' => 'in_progress'])->assertForbidden();
    }

    public function test_complaint_update_allows_status_with_transition_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName('complaints.transition', 'web'));
        $complaint = Complaint::create(['complaint_number' => 'CMP-920002', 'title' => 'شكوى', 'description' => 'اختبار', 'status' => 'open', 'priority' => 'medium', 'reported_by' => $user->id]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->putJson("/api/complaints/{$complaint->id}", ['status' => 'in_progress'])->assertOk();
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'in_progress']);
    }
}
