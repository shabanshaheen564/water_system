<?php

namespace App\Services;

use App\Models\Dataset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class MaintenanceAccessService
{
    public function datasetsFor(User $user): Builder
    {
        if ($user->hasAnyRole(['System Owner', 'Admin', 'Engineer'])) {
            return Dataset::query()
                ->where('is_active', true)
                ->where('is_spatial', true)
                ->where('maintenance_enabled', true)
                ->orderBy('display_name');
        }

        $roleIds = $user->roles()->pluck('roles.id');

        return Dataset::query()
            ->where('is_active', true)
            ->where('is_spatial', true)
            ->where('maintenance_enabled', true)
            ->whereHas('maintenanceRoles', fn (Builder $query) => $query->whereIn('roles.id', $roleIds))
            ->orderBy('display_name');
    }

    public function canAccessDataset(User $user, Dataset $dataset): bool
    {
        if (!$dataset->is_active || !$dataset->is_spatial || !$dataset->maintenance_enabled) {
            return false;
        }

        if ($user->hasAnyRole(['System Owner', 'Admin', 'Engineer'])) {
            return true;
        }

        return \Illuminate\Support\Facades\DB::table('maintenance_dataset_role')
            ->whereIn('role_id', $user->roles()->pluck('roles.id'))
            ->where('dataset_id', $dataset->id)
            ->exists();
    }

    public function canAccessFeature(User $user, \App\Models\GisFeature $feature): bool
    {
        $feature->loadMissing('dataset');

        return $feature->dataset !== null
            && $this->canAccessDataset($user, $feature->dataset);
    }

    public function canManageAllRequests(User $user): bool
    {
        return $user->hasAnyRole(['System Owner', 'Admin', 'Engineer']);
    }

    public function canAccessRequest(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        if ($this->canManageAllRequests($user)) {
            return true;
        }

        $maintenanceRequest->loadMissing('gisFeature.dataset');

        $feature = $maintenanceRequest->gisFeature;
        if ($feature === null || $feature->dataset === null) {
            return false;
        }

        $dataset = $feature->dataset;

        if (!$dataset->is_active || !$dataset->is_spatial || !$dataset->maintenance_enabled) {
            return false;
        }

        return (int) $maintenanceRequest->assigned_to === (int) $user->id
            && $this->canAccessDataset($user, $dataset);
    }

    public function canAssignRequests(User $user): bool
    {
        return $user->can('maintenance.assign');
    }

    public function canCancelRequests(User $user): bool
    {
        return $this->canManageAllRequests($user);
    }

    public function assertStatusTransition(MaintenanceRequest $maintenanceRequest, string $targetStatus): void
    {
        $currentStatus = $maintenanceRequest->status;

        $allowed = [
            'new' => ['assigned'],
            'assigned' => ['in_progress', 'waiting'],
            'in_progress' => ['waiting', 'not_repaired', 'completed'],
            'waiting' => ['in_progress', 'not_repaired', 'completed'],
            'not_repaired' => ['in_progress', 'waiting', 'completed'],
            'completed' => [],
            'cancelled' => [],
        ];

        if (!array_key_exists($currentStatus, $allowed) || !in_array($targetStatus, $allowed[$currentStatus], true)) {
            throw ValidationException::withMessages([
                'status' => "لا يمكن نقل طلب الصيانة من الحالة {$currentStatus} إلى {$targetStatus}.",
            ]);
        }
    }

    public function assertStatusPermission(User $user, string $targetStatus): void
    {
        if (in_array($targetStatus, ['completed', 'not_repaired'], true)
            && !$user->can('maintenance.complete')
        ) {
            abort(403);
        }

        if ($targetStatus === 'cancelled' && !$this->canCancelRequests($user)) {
            abort(403);
        }

        if (in_array($targetStatus, ['new', 'assigned', 'in_progress', 'waiting'], true)
            && !$user->can('maintenance.update')
        ) {
            abort(403);
        }
    }

    public function assertJobExecutionAllowed(User $user, MaintenanceRequest $maintenanceRequest): void
    {
        if (!$user->can('maintenance.complete')) {
            abort(403);
        }

        if (in_array($maintenanceRequest->status, ['new', 'completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن تنفيذ الصيانة على طلب بهذه الحالة.',
            ]);
        }
    }
}
