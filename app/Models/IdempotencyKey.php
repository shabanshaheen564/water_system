<?php

namespace AppModels;

use IlluminateDatabaseEloquentModel;
use IlluminateDatabaseEloquentRelationsBelongsTo;

class IdempotencyKey extends Model
{
    protected $fillable = ['user_id', 'key', 'endpoint', 'request_hash', 'response', 'status_code'];

    protected function casts(): array
    {
        return ['response' => 'array', 'status_code' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}