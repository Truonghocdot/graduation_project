<?php

namespace App\Models;

use Database\Factories\IdempotencyKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'actor_type',
    'actor_key',
    'user_id',
    'scope',
    'key',
    'request_hash',
    'status',
    'response_code',
    'response_body',
    'resource_type',
    'resource_id',
    'expires_at',
])]
class IdempotencyKey extends Model
{
    /** @use HasFactory<IdempotencyKeyFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'response_code' => 'integer',
            'resource_id' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
