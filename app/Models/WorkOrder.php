<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkOrder extends Model
{
    protected array $pendingComplaintIds = [];

    protected $fillable = [
        'work_order_number',
        'title',
        'description',
        'status',
        'priority',
        'assigned_to',
        'created_by',
        'started_at',
        'completed_at',
        'notes',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    /**
     * Accept legacy complaint_id input without restoring complaint_id as a
     * database column. The complaint_work_order pivot remains the only
     * persisted relationship source of truth.
     */
    public function fill(array $attributes)
    {
        if (array_key_exists('complaint_id', $attributes)) {
            $complaintId = $attributes['complaint_id'];
            unset($attributes['complaint_id']);

            if ($complaintId !== null && $complaintId !== '') {
                $this->pendingComplaintIds[] = (int) $complaintId;
            }
        }

        return parent::fill($attributes);
    }

    protected static function booted(): void
    {
        static::created(function (WorkOrder $workOrder): void {
            if ($workOrder->pendingComplaintIds !== []) {
                $workOrder->complaints()->syncWithoutDetaching($workOrder->pendingComplaintIds);
                $workOrder->pendingComplaintIds = [];
            }
        });
    }

    public function complaints(): BelongsToMany
    {
        return $this->belongsToMany(Complaint::class, 'complaint_work_order')
            ->withTimestamps();
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
