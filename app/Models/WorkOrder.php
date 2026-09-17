<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkOrder extends Model
{
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
