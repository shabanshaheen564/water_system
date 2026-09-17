<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ComplaintWorkOrderLinkingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::findByName('complaints.view', 'web'));
        $this->user->givePermissionTo(Permission::findByName('complaints.update', 'web'));
        $this->user->givePermissionTo(Permission::findByName('tasks.create', 'web'));
        $this->user->givePermissionTo(Permission::findByName('tasks.update', 'web'));
        $this->user->givePermissionTo(Permission::findByName('tasks.transition', 'web'));
        $this->user->givePermissionTo(Permission::findByName('tasks.view', 'web'));
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_can_add_a_second_complaint_to_an_existing_work_order(): void
    {
        $complaint1 = Complaint::create([
            'complaint_number' => 'CMP-910001', 'title' => 'تسرب أول', 'description' => 'تسرب في نفس الخط',
            'status' => 'open', 'priority' => 'high', 'reported_by' => $this->user->id,
        ]);
        $complaint2 = Complaint::create([
            'complaint_number' => 'CMP-910002', 'title' => 'تسرب ثان', 'description' => 'بلاغ آخر لنفس المشكلة',
            'status' => 'open', 'priority' => 'medium', 'reported_by' => $this->user->id,
        ]);

        $workOrder = WorkOrder::create([
            'work_order_number' => 'WO-910001', 'complaint_id' => $complaint1->id,
            'title' => 'إصلاح الخط الرئيسي', 'description' => 'إصلاح المشكلة المشتركة',
            'status' => 'in_progress', 'priority' => 'high', 'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get("/complaints/{$complaint2->id}/add-to-work-order")
            ->assertOk()
            ->assertSee($workOrder->work_order_number);

        $this->actingAs($this->user)
            ->post("/complaints/{$complaint2->id}/add-to-work-order", ['work_order_id' => $workOrder->id])
            ->assertRedirect("/complaints/{$complaint2->id}");

        $this->assertDatabaseHas('complaint_work_order', [
            'complaint_id' => $complaint1->id,
            'work_order_id' => $workOrder->id,
        ]);
        $this->assertDatabaseHas('complaint_work_order', [
            'complaint_id' => $complaint2->id,
            'work_order_id' => $workOrder->id,
        ]);
        $this->assertDatabaseHas('complaints', [
            'id' => $complaint2->id,
            'status' => 'in_progress',
            'assigned_to' => $this->user->id,
        ]);
        $this->assertCount(2, $workOrder->fresh()->complaints);
    }

    public function test_completed_work_order_closes_all_linked_complaints(): void
    {
        $complaints = collect([1, 2])->map(fn (int $number) => Complaint::create(['complaint_number' => 'CMP-91010' . $number, 'title' => 'شكوى مشتركة ' . $number, 'description' => 'المشكلة نفسها', 'status' => 'in_progress', 'priority' => 'medium', 'reported_by' => $this->user->id]));
        $workOrder = WorkOrder::create(['work_order_number' => 'WO-910010', 'title' => 'إصلاح المشكلة المشتركة', 'description' => 'معالجة واحدة', 'status' => 'in_progress', 'priority' => 'medium', 'assigned_to' => $this->user->id, 'created_by' => $this->user->id]);
        $workOrder->complaints()->attach($complaints->pluck('id'));

        $this->actingAs($this->user)->put("/work-orders/{$workOrder->id}", ['status' => 'completed', 'assigned_to' => $this->user->id, 'priority' => 'medium', 'notes' => 'تم التنفيذ.'])->assertRedirect("/work-orders/{$workOrder->id}");
        foreach ($complaints as $complaint) $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'closed', 'processed_by' => $this->user->id]);
    }
}
