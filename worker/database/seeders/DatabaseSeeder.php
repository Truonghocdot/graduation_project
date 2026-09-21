<?php

namespace Database\Seeders;

use App\Enums\RoleKey;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(VehicleTypeSeeder::class);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $customerRoleId = Role::query()
            ->where('key', RoleKey::Customer->value)
            ->value('id');

        $user->roles()->attach($customerRoleId, ['granted_at' => now()]);
    }
}
