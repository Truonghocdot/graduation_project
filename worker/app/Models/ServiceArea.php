<?php

namespace App\Models;

use App\Enums\ServiceType;
use Database\Factories\ServiceAreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property ServiceType|null $service_type
 * @property array<string, mixed> $boundary
 * @property bool $is_active
 */
#[Fillable(['name', 'service_type', 'boundary', 'is_active'])]
class ServiceArea extends Model
{
    /** @use HasFactory<ServiceAreaFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'boundary' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
