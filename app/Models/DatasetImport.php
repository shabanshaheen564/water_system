<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetImport extends Model
{
    protected $fillable = [
        'dataset_id',
        'original_filename',
        'source_format',
        'imported_by',
        'started_at',
        'completed_at',
        'status',
        'total_rows',
        'successful_rows',
        'failed_rows',
        'error_summary',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_rows' => 'integer',
            'successful_rows' => 'integer',
            'failed_rows' => 'integer',
            'error_summary' => 'array',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}