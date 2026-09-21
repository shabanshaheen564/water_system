<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Complaint extends Model
{
    protected $fillable = [
        'complaint_number','title','description','processing_notes','solution','status','priority',
        'reported_by','assigned_to','processed_by','contact_name','contact_phone','address',
        'latitude','longitude','resolved_at','processed_at','first_response_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8','longitude' => 'decimal:8',
            'resolved_at' => 'datetime','processed_at' => 'datetime','first_response_at' => 'datetime',
        ];
    }

    public function reportedBy(): BelongsTo { return $this->belongsTo(User::class, 'reported_by'); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function processedBy(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }

    public function gisFeatures(): BelongsToMany
    {
        return $this->belongsToMany(GisFeature::class, 'complaint_gis_feature')->withPivot('created_by')->withTimestamps();
    }

    public function workOrders(): BelongsToMany
    {
        return $this->belongsToMany(WorkOrder::class, 'complaint_work_order')->withTimestamps();
    }
}
