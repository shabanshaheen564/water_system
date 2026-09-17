<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ComplaintWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo([
            Permission::findByName('complaints.view', 'web'),
            Permission::findByName('complaints.create', 'web'),
            Permission::findByName('complaints.update', 'web'),
            Permission::findByName('complaints.delete', 'web'),
        ]);
    }

    public function test_guest_cannot_access_complaints_web_page(): void { $this->get('/complaints')->assertRedirect('/login'); }
    public function test_user_without_view_permission_is_forbidden(): void { $this->actingAs(User::factory()->create())->get('/complaints')->assertForbidden(); }
    public function test_user_with_view_permission_can_list_complaints(): void { $this->actingAs($this->user)->get('/complaints')->assertOk()->assertSee('الشكاوى'); }
    public function test_user_with_create_permission_can_open_creation_form(): void { $this->actingAs($this->user)->get('/complaints/create')->assertOk()->assertSee('تسجيل شكوى جديدة'); }

    public function test_complaint_assignment_list_includes_active_field_worker(): void
    {
        $fieldWorker = User::factory()->create(['name' => 'عامل ميداني للاختبار', 'is_active' => true]);
        $fieldWorker->assignRole('Field Worker');
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-900009', 'title' => 'شكوى اختبار الإسناد', 'description' => 'وصف',
            'status' => 'open', 'priority' => 'medium', 'reported_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get("/complaints/{$complaint->id}/edit")
            ->assertOk()
            ->assertSee('عامل ميداني للاختبار');
    }

    public function test_user_can_create_complaint_from_web_form(): void
    {
        $this->actingAs($this->user)->post('/complaints', [
            'title' => 'تسرب مياه', 'description' => 'تسرب واضح بالقرب من الشارع الرئيسي', 'priority' => 'high',
            'contact_name' => 'أحمد', 'contact_phone' => '0599000000', 'address' => 'دير البلح', 'latitude' => 31.417, 'longitude' => 34.352,
        ])->assertRedirect('/complaints');
        $this->assertDatabaseHas('complaints', ['title' => 'تسرب مياه', 'status' => 'open', 'priority' => 'high', 'reported_by' => $this->user->id]);
    }

    public function test_user_can_assign_complaint_and_record_processing(): void
    {
        $assignee = User::factory()->create(['is_active' => true]);
        $assignee->givePermissionTo(Permission::findByName('complaints.update', 'web'));
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-900010', 'title' => 'انقطاع مياه', 'description' => 'انقطاع في المنطقة',
            'status' => 'open', 'priority' => 'high', 'reported_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)->put("/complaints/{$complaint->id}", [
            'title' => $complaint->title, 'description' => $complaint->description, 'status' => 'in_progress',
            'priority' => 'high', 'assigned_to' => $assignee->id,
            'processing_notes' => 'تم فحص الخط وتحديد موقع العطل.', 'solution' => 'تم إصلاح العطل وإعادة ضخ المياه.',
        ])->assertRedirect("/complaints/{$complaint->id}");

        $fresh = $complaint->fresh();
        $this->assertSame($assignee->id, $fresh->assigned_to);
        $this->assertSame('in_progress', $fresh->status);
        $this->assertSame('تم فحص الخط وتحديد موقع العطل.', $fresh->processing_notes);
        $this->assertSame('تم إصلاح العطل وإعادة ضخ المياه.', $fresh->solution);
        $this->assertSame($this->user->id, $fresh->processed_by);
        $this->assertNotNull($fresh->processed_at);
    }

    public function test_processing_can_resolve_complaint_and_sets_resolution_time(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-900011', 'title' => 'ضغط مياه منخفض', 'description' => 'ضغط منخفض',
            'status' => 'in_progress', 'priority' => 'medium', 'reported_by' => $this->user->id,
        ]);
        $this->actingAs($this->user)->put("/complaints/{$complaint->id}", [
            'status' => 'resolved', 'processing_notes' => 'تمت المعالجة.', 'solution' => 'تمت إعادة الخدمة.',
        ])->assertRedirect("/complaints/{$complaint->id}");
        $fresh = $complaint->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertNotNull($fresh->resolved_at);
        $this->assertNotNull($fresh->processed_at);
    }

    public function test_inactive_user_cannot_be_assigned(): void
    {
        $inactive = User::factory()->create(['is_active' => false]);
        $complaint = Complaint::create(['complaint_number' => 'CMP-900012', 'title' => 'شكوى', 'description' => 'وصف', 'status' => 'open', 'priority' => 'medium', 'reported_by' => $this->user->id]);
        $this->actingAs($this->user)->put("/complaints/{$complaint->id}", ['assigned_to' => $inactive->id])->assertStatus(422);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $complaint = Complaint::create(['complaint_number' => 'CMP-900002', 'title' => 'شكوى', 'description' => 'وصف', 'status' => 'open', 'priority' => 'medium', 'reported_by' => $this->user->id]);
        $this->actingAs($this->user)->put("/complaints/{$complaint->id}", ['status' => 'closed'])->assertSessionHasErrors('status');
    }

    public function test_user_can_delete_complaint_without_work_order(): void
    {
        $complaint = Complaint::create(['complaint_number' => 'CMP-900003', 'title' => 'حذف', 'description' => 'حذف', 'status' => 'open', 'priority' => 'low', 'reported_by' => $this->user->id]);
        $this->actingAs($this->user)->delete("/complaints/{$complaint->id}")->assertRedirect('/complaints');
        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
    }
}
