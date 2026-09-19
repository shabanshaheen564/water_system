<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ArchivedWorkOrder extends Model
{
    protected $fillable = [
        'original_id','work_order_number','title','description','status','priority',
        'assigned_to','created_by','started_at','completed_at','notes','latitude','longitude',
        'original_created_at','original_updated_at','archived_at',
        'response_time_minutes','execution_time_minutes','total_time_minutes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'original_created_at' => 'datetime',
            'original_updated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function complaints(): BelongsToMany
    {
        return $this->belongsToMany(ArchivedComplaint::class, 'archived_complaint_work_order')
            ->withTimestamps();
    }
}
