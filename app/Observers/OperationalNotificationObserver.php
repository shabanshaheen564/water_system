<?php

namespace App\Observers;

use App\Models\Complaint;
use App\Models\WorkOrder;
use App\Services\FcmService;
use Illuminate\Support\Facades\DB;

class OperationalNotificationObserver
{
    public function created(Complaint|WorkOrder $model): void
    {
        $assignedTo = $model->assigned_to;
        if (!$assignedTo) {
            return;
        }

        if ($model instanceof Complaint) {
            $title = 'شكوى جديدة';
            $body = "تم إسناد الشكوى {$model->complaint_number} إليك.";
            $type = 'complaint_assigned';
            $id = $model->id;
        } else {
            $title = 'مهمة جديدة';
            $body = "تم إسناد المهمة {$model->work_order_number} إليك.";
            $type = 'work_order_assigned';
            $id = $model->id;
        }

        $this->sendAfterCommit((int) $assignedTo, $title, $body, $type, (int) $id);
    }

    public function updated(Complaint|WorkOrder $model): void
    {
        if (!$model->assigned_to) {
            return;
        }

        if ($model->wasChanged('assigned_to')) {
            if ($model instanceof Complaint) {
                $title = 'تم إسناد شكوى';
                $body = "تم إسناد الشكوى {$model->complaint_number} إليك.";
                $type = 'complaint_assigned';
            } else {
                $title = 'تم إسناد مهمة';
                $body = "تم إسناد المهمة {$model->work_order_number} إليك.";
                $type = 'work_order_assigned';
            }
        } elseif ($model->wasChanged('status')) {
            if ($model instanceof Complaint) {
                $title = 'تحديث شكوى';
                $body = "تم تحديث حالة الشكوى {$model->complaint_number}.";
                $type = 'complaint_status';
            } else {
                $title = 'تحديث مهمة';
                $body = "تم تحديث حالة المهمة {$model->work_order_number}.";
                $type = 'work_order_status';
            }
        } else {
            return;
        }

        $this->sendAfterCommit((int) $model->assigned_to, $title, $body, $type, (int) $model->id);
    }

    private function sendAfterCommit(int $userId, string $title, string $body, string $type, int $entityId): void
    {
        DB::afterCommit(function () use ($userId, $title, $body, $type, $entityId): void {
            app(FcmService::class)->sendToUser($userId, $title, $body, [
                'type' => $type,
                'entity_id' => (string) $entityId,
            ]);
        });
    }
}
