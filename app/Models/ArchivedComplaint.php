<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ArchivedComplaint extends Model
{
    protected $fillable = [
        'original_id','complaint_number','title','description','processing_notes','solution',
        'status','priority','reported_by','assigned_to','processed_by','contact_name','contact_phone',
        'address','latitude','longitude','first_response_at','processed_at','resolved_at',
        'original_created_at','original_updated_at','archived_at',
        'response_time_minutes','resolution_time_minutes','total_time_minutes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'first_response_at' => 'datetime',
            'processed_at' => 'datetime',
            'resolved_at' => 'datetime',
            'original_created_at' => 'datetime',
            'original_updated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function reportedBy(): BelongsTo { return $this->belongsTo(User::class, 'reported_by'); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function processedBy(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }

    public function workOrders(): BelongsToMany
    {
        return $this->belongsToMany(ArchivedWorkOrder::class, 'archived_complaint_work_order')
            ->withTimestamps();
    }
}
