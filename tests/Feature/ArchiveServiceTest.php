<?php

namespace Tests\Feature;

use App\Models\ArchivedComplaint;
use App\Models\ArchivedWorkOrder;
use App\Models\Complaint;
use App\Models\WorkOrder;
use App\Services\ArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_complaint_and_completed_work_order_are_archived_with_metrics_and_relationship(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-ARCH-001',
            'title' => 'Test complaint',
            'description' => 'Archive test',
            'status' => 'closed',
            'priority' => 'high',
        ]);

        $created = now()->subHours(3);
        $complaint->forceFill([
            'created_at' => $created,
            'first_response_at' => $created->copy()->addMinutes(30),
            'resolved_at' => $created->copy()->addHours(2),
        ])->save();

        $workOrder = WorkOrder::create([
            'work_order_number' => 'WO-ARCH-001',
            'title' => 'Test task',
            'description' => 'Archive test',
            'status' => 'completed',
            'priority' => 'high',
            'created_by' => null,
        ]);

        $workOrder->forceFill([
            'created_at' => $created->copy()->addMinutes(10),
            'started_at' => $created->copy()->addMinutes(40),
            'completed_at' => $created->copy()->addHours(2),
        ])->save();

        $workOrder->complaints()->attach($complaint->id);

        app(ArchiveService::class)->archiveClosedComplaint($complaint->fresh());

        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
        $this->assertDatabaseMissing('work_orders', ['id' => $workOrder->id]);
        $this->assertDatabaseHas('archived_complaints', [
            'original_id' => $complaint->id,
            'response_time_minutes' => 30,
            'resolution_time_minutes' => 120,
            'total_time_minutes' => 120,
        ]);
        $this->assertDatabaseHas('archived_work_orders', [
            'original_id' => $workOrder->id,
            'response_time_minutes' => 30,
            'execution_time_minutes' => 80,
            'total_time_minutes' => 110,
        ]);

        $archivedComplaint = ArchivedComplaint::where('original_id', $complaint->id)->firstOrFail();
        $archivedWorkOrder = ArchivedWorkOrder::where('original_id', $workOrder->id)->firstOrFail();

        $this->assertDatabaseHas('archived_complaint_work_order', [
            'archived_complaint_id' => $archivedComplaint->id,
            'archived_work_order_id' => $archivedWorkOrder->id,
        ]);
    }

    public function test_completed_work_order_with_active_complaint_is_not_archived(): void
    {
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-ARCH-002',
            'title' => 'Active complaint',
            'description' => 'Archive test',
            'status' => 'in_progress',
            'priority' => 'medium',
        ]);

        $workOrder = WorkOrder::create([
            'work_order_number' => 'WO-ARCH-002',
            'title' => 'Test task',
            'description' => 'Archive test',
            'status' => 'completed',
            'priority' => 'medium',
        ]);
        $workOrder->complaints()->attach($complaint->id);

        app(ArchiveService::class)->archiveCompletedWorkOrder($workOrder->fresh());

        $this->assertDatabaseHas('work_orders', ['id' => $workOrder->id]);
        $this->assertDatabaseMissing('archived_work_orders', ['original_id' => $workOrder->id]);
    }
}
