<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dataset extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'dataset_type',
        'source_name',
        'source_format',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DatasetField::class)->orderBy('sort_order');
    }

    public function records(): HasMany
    {
        return $this->hasMany(DatasetRecord::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(DatasetImport::class);
    }

    public function parentRelationships(): HasMany
    {
        return $this->hasMany(DatasetRelationship::class, 'parent_dataset_id');
    }

    public function childRelationships(): HasMany
    {
        return $this->hasMany(DatasetRelationship::class, 'child_dataset_id');
    }

    public function getIdentifierField(): ?DatasetField
    {
        return $this->fields()->where('is_identifier', true)->first();
    }
}