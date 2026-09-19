<?php

namespace App\Services;

use App\Models\ArchivedComplaint;
use App\Models\ArchivedWorkOrder;
use App\Models\Complaint;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class ArchiveService
{
    public function archiveCompletedWorkOrder(WorkOrder $workOrder): ?ArchivedWorkOrder
    {
        if ($workOrder->status !== 'completed') return null;

        $workOrder->loadMissing('complaints');
        if ($workOrder->complaints->contains(fn ($complaint) => $complaint->status !== 'closed')) {
            return null;
        }

        return DB::transaction(function () use ($workOrder) {
            $workOrder = WorkOrder::query()->lockForUpdate()->with('complaints')->find($workOrder->id);
            if (!$workOrder || $workOrder->status !== 'completed') return null;
            if ($workOrder->complaints->contains(fn ($complaint) => $complaint->status !== 'closed')) return null;

            $archived = ArchivedWorkOrder::firstOrCreate(
                ['original_id' => $workOrder->id],
                $this->workOrderPayload($workOrder)
            );

            $complaintIds = $workOrder->complaints->pluck('id');
            DB::table('complaint_work_order')->where('work_order_id', $workOrder->id)->delete();
            $workOrder->delete();

            foreach ($complaintIds as $complaintId) {
                $archivedComplaint = ArchivedComplaint::where('original_id', $complaintId)->first();
                if ($archivedComplaint) {
                    DB::table('archived_complaint_work_order')->insertOrIgnore([
                        'archived_complaint_id' => $archivedComplaint->id,
                        'archived_work_order_id' => $archived->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return $archived;
        });
    }

    public function archiveClosedComplaint(Complaint $complaint): ?ArchivedComplaint
    {
        if ($complaint->status !== 'closed') return null;

        return DB::transaction(function () use ($complaint) {
            $complaint = Complaint::query()->lockForUpdate()->with('workOrders')->find($complaint->id);
            if (!$complaint || $complaint->status !== 'closed') return null;

            $originalWorkOrderIds = $complaint->workOrders->pluck('id')->all();
            foreach ($complaint->workOrders as $workOrder) {
                if ($workOrder->status === 'completed') $this->archiveCompletedWorkOrder($workOrder);
            }

            $complaint->load('workOrders');
            if ($complaint->workOrders->contains(fn ($workOrder) => $workOrder->status !== 'completed')) return null;

            $archived = ArchivedComplaint::firstOrCreate(
                ['original_id' => $complaint->id],
                $this->complaintPayload($complaint)
            );

            $archivedWorkOrders = ArchivedWorkOrder::whereIn('original_id', $originalWorkOrderIds)->get();

            DB::table('complaint_work_order')->where('complaint_id', $complaint->id)->delete();
            $complaint->delete();

            foreach ($archivedWorkOrders as $archivedWorkOrder) {
                DB::table('archived_complaint_work_order')->insertOrIgnore([
                    'archived_complaint_id' => $archived->id,
                    'archived_work_order_id' => $archivedWorkOrder->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $archived;
        });
    }

    public function archiveEligibleForComplaint(Complaint $complaint): void
    {
        if ($complaint->status === 'closed') $this->archiveClosedComplaint($complaint);
    }

    private function complaintPayload(Complaint $complaint): array
    {
        $created = $complaint->created_at;
        $firstResponse = $complaint->first_response_at;
        $resolved = $complaint->resolved_at;

        return [
            'complaint_number' => $complaint->complaint_number,
            'title' => $complaint->title,
            'description' => $complaint->description,
            'processing_notes' => $complaint->processing_notes,
            'solution' => $complaint->solution,
            'status' => $complaint->status,
            'priority' => $complaint->priority,
            'reported_by' => $complaint->reported_by,
            'assigned_to' => $complaint->assigned_to,
            'processed_by' => $complaint->processed_by,
            'contact_name' => $complaint->contact_name,
            'contact_phone' => $complaint->contact_phone,
            'address' => $complaint->address,
            'latitude' => $complaint->latitude,
            'longitude' => $complaint->longitude,
            'first_response_at' => $firstResponse,
            'processed_at' => $complaint->processed_at,
            'resolved_at' => $resolved,
            'original_created_at' => $created,
            'original_updated_at' => $complaint->updated_at,
            'archived_at' => now(),
            'response_time_minutes' => $this->minutesBetween($created, $firstResponse),
            'resolution_time_minutes' => $this->minutesBetween($created, $resolved),
            'total_time_minutes' => $this->minutesBetween($created, $resolved),
        ];
    }

    private function workOrderPayload(WorkOrder $workOrder): array
    {
        return [
            'work_order_number' => $workOrder->work_order_number,
            'title' => $workOrder->title,
            'description' => $workOrder->description,
            'status' => $workOrder->status,
            'priority' => $workOrder->priority,
            'assigned_to' => $workOrder->assigned_to,
            'created_by' => $workOrder->created_by,
            'started_at' => $workOrder->started_at,
            'completed_at' => $workOrder->completed_at,
            'notes' => $workOrder->notes,
            'latitude' => $workOrder->latitude,
            'longitude' => $workOrder->longitude,
            'original_created_at' => $workOrder->created_at,
            'original_updated_at' => $workOrder->updated_at,
            'archived_at' => now(),
            'response_time_minutes' => $this->minutesBetween($workOrder->created_at, $workOrder->started_at),
            'execution_time_minutes' => $this->minutesBetween($workOrder->started_at, $workOrder->completed_at),
            'total_time_minutes' => $this->minutesBetween($workOrder->created_at, $workOrder->completed_at),
        ];
    }

    private function minutesBetween($from, $to): ?int
    {
        if (!$from || !$to) return null;
        return $from->diffInMinutes($to);
    }
}
