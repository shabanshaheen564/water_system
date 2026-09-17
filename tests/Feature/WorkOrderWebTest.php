<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkOrderWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::findByName('tasks.view', 'web'));
        $this->user->givePermissionTo(Permission::findByName('tasks.update', 'web'));
        $this->user->givePermissionTo(Permission::findByName('complaints.view', 'web'));
    }

    public function test_user_can_view_work_order_list_and_details_with_linked_complaints(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-910001', 'title' => 'تسرب في الخط', 'description' => 'تسرب مياه',
            'status' => 'in_progress', 'priority' => 'high', 'reported_by' => $this->user->id,
        ]);
        $workOrder = WorkOrder::create([
            'work_order_number' => 'WO-910001', 'complaint_id' => $complaint->id,
            'title' => 'إصلاح التسرب', 'description' => 'إصلاح الخط', 'status' => 'assigned',
            'priority' => 'high', 'assigned_to' => $this->user->id, 'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get('/work-orders')
            ->assertOk()
            ->assertSee('المهام')
            ->assertSee('WO-910001')
            ->assertSee('إصلاح التسرب');

        $this->actingAs($this->user)
            ->get("/work-orders/{$workOrder->id}")
            ->assertOk()
            ->assertSee('الشكاوى المرتبطة')
            ->assertSee('CMP-910001')
            ->assertSee('تسرب في الخط');
    }

    public function test_completing_work_order_from_web_closes_all_linked_complaints(): void
    {
        $complaints = collect([1, 2])->map(function (int $number) {
            return Complaint::create([
                'complaint_number' => 'CMP-91000' . $number, 'title' => 'شكوى مشتركة ' . $number,
                'description' => 'المشكلة نفسها', 'status' => 'in_progress', 'priority' => 'medium',
                'reported_by' => $this->user->id,
            ]);
        });

        $workOrder = WorkOrder::create([
            'work_order_number' => 'WO-910002', 'title' => 'إصلاح المشكلة المشتركة', 'description' => 'معالجة واحدة',
            'status' => 'in_progress', 'priority' => 'medium', 'assigned_to' => $this->user->id, 'created_by' => $this->user->id,
        ]);
        $workOrder->complaints()->attach($complaints->pluck('id'));

        $this->actingAs($this->user)
            ->put("/work-orders/{$workOrder->id}", [
                'status' => 'completed', 'assigned_to' => $this->user->id, 'priority' => 'medium', 'notes' => 'تم التنفيذ.',
            ])
            ->assertRedirect("/work-orders/{$workOrder->id}");

        foreach ($complaints as $complaint) {
            $this->assertDatabaseHas('complaints', [
                'id' => $complaint->id, 'status' => 'closed', 'processed_by' => $this->user->id,
            ]);
        }
        $this->assertDatabaseHas('work_orders', ['id' => $workOrder->id, 'status' => 'completed']);
    }

    public function test_inactive_user_cannot_be_assigned_from_work_order_page(): void
    {
        $inactive = User::factory()->create(['is_active' => false]);
        $workOrder = WorkOrder::create([
            'work_order_number' => 'WO-910003', 'title' => 'مهمة اختبار', 'description' => 'اختبار',
            'status' => 'assigned', 'priority' => 'medium', 'assigned_to' => $this->user->id, 'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->put("/work-orders/{$workOrder->id}", [
                'status' => 'assigned', 'assigned_to' => $inactive->id, 'priority' => 'medium',
            ])
            ->assertStatus(422);
    }
}
