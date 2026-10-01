<?php

namespace App\Services;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

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

    public function canAccessRequest(User $user, \App\Models\MaintenanceRequest $maintenanceRequest): bool
    {
        $maintenanceRequest->loadMissing('gisFeature.dataset');

        if (
            $maintenanceRequest->gisFeature === null
            || !$this->canAccessFeature($user, $maintenanceRequest->gisFeature)
        ) {
            return false;
        }

        if ($this->canManageAllRequests($user)) {
            return true;
        }

        return (int) $maintenanceRequest->assigned_to === (int) $user->id;
    }

    public function canAssignRequests(User $user): bool
    {
        return $user->can('maintenance.assign');
    }
}
