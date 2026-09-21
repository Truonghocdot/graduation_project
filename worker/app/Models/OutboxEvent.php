<?php

namespace App\Models;

use Database\Factories\OutboxEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'event_id',
    'event_type',
    'aggregate_type',
    'aggregate_id',
    'aggregate_version',
    'payload',
    'status',
    'attempt_count',
    'available_at',
    'published_at',
    'last_error',
])]
class OutboxEvent extends Model
{
    /** @use HasFactory<OutboxEventFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (OutboxEvent $event): void {
            $event->event_id ??= (string) Str::uuid();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'aggregate_id' => 'integer',
            'aggregate_version' => 'integer',
            'attempt_count' => 'integer',
            'available_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }
}
