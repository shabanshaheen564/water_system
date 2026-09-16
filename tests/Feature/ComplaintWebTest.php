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

    public function test_guest_cannot_access_complaints_web_page(): void
    {
        $this->get('/complaints')->assertRedirect('/login');
    }

    public function test_user_without_view_permission_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/complaints')
            ->assertForbidden();
    }

    public function test_user_with_view_permission_can_list_complaints(): void
    {
        $this->actingAs($this->user)->get('/complaints')->assertOk()->assertSee('الشكاوى');
    }

    public function test_user_with_create_permission_can_open_creation_form(): void
    {
        $this->actingAs($this->user)->get('/complaints/create')->assertOk()->assertSee('تسجيل شكوى جديدة');
    }

    public function test_user_can_create_complaint_from_web_form(): void
    {
        $this->actingAs($this->user)->post('/complaints', [
            'title' => 'تسرب مياه',
            'description' => 'تسرب واضح بالقرب من الشارع الرئيسي',
            'priority' => 'high',
            'contact_name' => 'أحمد',
            'contact_phone' => '0599000000',
            'address' => 'دير البلح',
            'latitude' => 31.417,
            'longitude' => 34.352,
        ])->assertRedirect('/complaints');

        $this->assertDatabaseHas('complaints', [
            'title' => 'تسرب مياه',
            'status' => 'open',
            'priority' => 'high',
            'reported_by' => $this->user->id,
        ]);
    }

    public function test_user_can_update_complaint_and_resolve_it(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-900001',
            'title' => 'ضغط مياه منخفض',
            'description' => 'ضغط منخفض',
            'status' => 'in_progress',
            'priority' => 'medium',
            'reported_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)->put("/complaints/{$complaint->id}", [
            'title' => 'ضغط مياه منخفض',
            'description' => 'تمت معالجة المشكلة',
            'status' => 'resolved',
            'priority' => 'medium',
        ])->assertRedirect("/complaints/{$complaint->id}");

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'resolved',
        ]);
        $this->assertNotNull($complaint->fresh()->resolved_at);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-900002',
            'title' => 'شكوى',
            'description' => 'وصف',
            'status' => 'open',
            'priority' => 'medium',
            'reported_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)->put("/complaints/{$complaint->id}", [
            'status' => 'closed',
        ])->assertSessionHasErrors('status');
    }

    public function test_user_can_delete_complaint_without_work_order(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-900003',
            'title' => 'حذف',
            'description' => 'حذف',
            'status' => 'open',
            'priority' => 'low',
            'reported_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)->delete("/complaints/{$complaint->id}")->assertRedirect('/complaints');
        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
    }
}
