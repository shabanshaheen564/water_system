<?php

namespace App\Services;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceAccessService
{
    public function datasetsFor(User $user): Builder
    {
        $roleIds = $user->roles()->pluck('roles.id');

        return Dataset::query()
            ->where('is_active', true)
            ->where('is_spatial', true)
            ->whereHas('maintenanceRoles', fn (Builder $query) => $query->whereIn('roles.id', $roleIds))
            ->orderBy('display_name');
    }

    public function canAccessDataset(User $user, Dataset $dataset): bool
    {
        if (!$dataset->is_active || !$dataset->is_spatial) {
            return false;
        }

        return $user->roles()
            ->whereHas('maintenanceDatasets', fn (Builder $query) => $query->whereKey($dataset->id))
            ->exists();
    }

    public function canAccessFeature(User $user, \App\Models\GisFeature $feature): bool
    {
        $feature->loadMissing('dataset');

        return $feature->dataset !== null
            && $this->canAccessDataset($user, $feature->dataset);
    }
}
