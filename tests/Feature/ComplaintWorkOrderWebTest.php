<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ComplaintWorkOrderWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::findByName('complaints.view', 'web'));
        $this->user->givePermissionTo(Permission::findByName('tasks.create', 'web'));
    }

    public function test_user_can_convert_complaint_to_work_order_and_assign_it_to_self(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-900013', 'title' => 'كسر خط مياه', 'description' => 'كسر في الخط الرئيسي',
            'status' => 'open', 'priority' => 'high', 'reported_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get("/complaints/{$complaint->id}/convert-to-work-order")
            ->assertOk()
            ->assertSee('تحويل الشكوى إلى مهمة');

        $this->actingAs($this->user)
            ->post("/complaints/{$complaint->id}/work-order", [
                'title' => 'معالجة كسر خط المياه',
                'description' => 'فحص الموقع وإصلاح الكسر وإعادة الخدمة.',
                'priority' => 'high',
                'assigned_to' => $this->user->id,
                'notes' => 'مهمة منشأة من الشكوى.',
            ])
            ->assertRedirect("/complaints/{$complaint->id}");

        $this->assertDatabaseHas('work_orders', [
            'complaint_id' => $complaint->id,
            'title' => 'معالجة كسر خط المياه',
            'status' => 'assigned',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);
        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'in_progress',
            'assigned_to' => $this->user->id,
        ]);
    }

    public function test_complaint_cannot_be_converted_to_more_than_one_work_order(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-900014', 'title' => 'ضعف ضغط المياه', 'description' => 'ضغط منخفض',
            'status' => 'open', 'priority' => 'medium', 'reported_by' => $this->user->id,
        ]);

        WorkOrder::create([
            'work_order_number' => 'WO-900014', 'complaint_id' => $complaint->id,
            'title' => 'مهمة موجودة', 'description' => 'مهمة مرتبطة مسبقاً', 'status' => 'assigned',
            'priority' => 'medium', 'assigned_to' => $this->user->id, 'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get("/complaints/{$complaint->id}/convert-to-work-order")
            ->assertStatus(422);

        $this->actingAs($this->user)
            ->post("/complaints/{$complaint->id}/work-order", [
                'title' => 'مهمة ثانية', 'description' => 'يجب رفضها', 'priority' => 'medium', 'assigned_to' => $this->user->id,
            ])
            ->assertSessionHasErrors('work_order');

        $this->assertSame(1, $complaint->workOrders()->count());
    }

    public function test_inactive_user_cannot_receive_complaint_work_order(): void
    {
        $inactive = User::factory()->create(['is_active' => false]);
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-900015', 'title' => 'تسرب', 'description' => 'تسرب مياه',
            'status' => 'open', 'priority' => 'high', 'reported_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->post("/complaints/{$complaint->id}/work-order", [
                'title' => 'إصلاح التسرب', 'description' => 'إصلاح', 'priority' => 'high', 'assigned_to' => $inactive->id,
            ])
            ->assertStatus(422);

        $this->assertSame(0, $complaint->workOrders()->count());
    }
}
