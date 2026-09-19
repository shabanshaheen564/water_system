<?php

namespace App\Console\Commands;

use App\Models\ArchivedComplaint;
use App\Models\ArchivedWorkOrder;
use App\Models\Complaint;
use App\Models\WorkOrder;
use App\Services\ArchiveService;
use Illuminate\Console\Command;

class ArchiveCompletedRecords extends Command
{
    protected $signature = 'archive:completed-records {--dry-run : Report eligible records without moving them}';
    protected $description = 'Move completed work orders and closed complaints to the archive tables.';

    public function handle(ArchiveService $archive): int
    {
        $completedTasks = WorkOrder::query()->where('status', 'completed')->get();
        $closedComplaints = Complaint::query()->where('status', 'closed')->get();

        $eligibleTasks = $completedTasks->filter(fn ($task) => $archive->archiveCompletedWorkOrderEligibility($task));
        $eligibleComplaints = $closedComplaints->filter(fn ($complaint) => $archive->archiveClosedComplaintEligibility($complaint));

        $this->info("Eligible completed tasks: {$eligibleTasks->count()}");
        $this->info("Eligible closed complaints: {$eligibleComplaints->count()}");

        if ($this->option('dry-run')) return self::SUCCESS;

        foreach ($eligibleTasks as $task) $archive->archiveCompletedWorkOrder($task);
        foreach ($eligibleComplaints as $complaint) {
            $fresh = Complaint::find($complaint->id);
            if ($fresh) $archive->archiveClosedComplaint($fresh);
        }

        $this->info('Archive migration completed.');
        $this->info('Archived complaints: '.ArchivedComplaint::query()->count());
        $this->info('Archived work orders: '.ArchivedWorkOrder::query()->count());

        return self::SUCCESS;
    }
}
