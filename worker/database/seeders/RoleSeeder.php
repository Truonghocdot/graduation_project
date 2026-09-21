<?php

namespace Database\Seeders;

use App\Enums\RoleKey;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (RoleKey::cases() as $role) {
            Role::query()->updateOrCreate(
                ['key' => $role->value],
                ['name' => Str::headline($role->value)],
            );
        }
    }
}
