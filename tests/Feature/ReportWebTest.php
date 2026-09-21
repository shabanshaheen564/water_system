<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $reportUser;
    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $this->reportUser = User::factory()->create();
        $this->reportUser->givePermissionTo('reports.view');

        $this->viewer = User::factory()->create();
    }

    public function test_reports_page_is_accessible_to_authorized_user(): void
    {
        $response = $this->actingAs($this->reportUser)->get('/reports');

        $response->assertStatus(200)
            ->assertViewIs('reports.index')
            ->assertViewHas(['summary', 'filters', 'requestFilters']);
    }

    public function test_reports_page_denied_to_unauthenticated_user(): void
    {
        $this->get('/reports')->assertStatus(302);
    }

    public function test_reports_page_denied_without_reports_permission(): void
    {
        $this->actingAs($this->viewer)->get('/reports')->assertStatus(403);
    }

    public function test_reports_filters_are_applied_to_summary_request(): void
    {
        $response = $this->actingAs($this->reportUser)->get('/reports?date_from=2026-01-01&date_to=2026-01-31&complaint_status=open&task_status=pending');

        $response->assertStatus(200)
            ->assertViewHas('requestFilters', function (array $filters): bool {
                return $filters['date_from'] === '2026-01-01'
                    && $filters['date_to'] === '2026-01-31'
                    && $filters['complaint_status'] === 'open'
                    && $filters['task_status'] === 'pending';
            });
    }
}
