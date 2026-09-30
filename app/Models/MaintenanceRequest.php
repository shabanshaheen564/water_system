<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceRequest extends Model
{
    protected $fillable = [
        'request_no',
        'gis_feature_id',
        'reported_by',
        'assigned_to',
        'priority',
        'status',
        'problem_description',
        'fault_description',
        'requested_at',
        'completed_at',
        'repair_result',
        'repair_action',
        'materials_used',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MaintenanceRequest $request): void {
            if (!$request->request_no) {
                $next = (int) \Illuminate\Support\Facades\DB::selectOne(
                    "SELECT nextval('maintenance_requests_number_seq') AS value"
                )->value;

                $request->request_no = 'MNT-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function gisFeature(): BelongsTo
    {
        return $this->belongsTo(GisFeature::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(MaintenanceJob::class);
    }
}
