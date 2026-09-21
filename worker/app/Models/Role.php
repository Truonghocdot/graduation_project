<?php

namespace App\Models;

use App\Enums\RoleKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property RoleKey $key
 * @property string $name
 */
#[Fillable(['key', 'name'])]
class Role extends Model
{
    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot(['granted_by', 'granted_at']);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => RoleKey::class,
        ];
    }
}
