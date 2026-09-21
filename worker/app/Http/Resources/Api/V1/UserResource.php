<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'phone_verified_at' => $this->phone_verified_at?->toISOString(),
            'status' => $this->status->value,
            'roles' => $this->whenLoaded(
                'roles',
                fn () => $this->roles
                    ->map(fn (Role $role): string => $role->key->value)
                    ->values(),
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
