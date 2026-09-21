<?php

namespace App\Console\Commands;

use App\Enums\RoleKey;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('app:create-admin-user
    {phone : Vietnamese phone number}
    {--name=Administrator : Display name}
    {--password= : Password; prompted securely when omitted}')]
#[Description('Create or promote an active Filament administrator')]
class CreateAdminUser extends Command
{
    public function handle(): int
    {
        $phone = PhoneNumber::normalize((string) $this->argument('phone'));
        $password = $this->option('password') ?: $this->secret('Password');
        $data = [
            'phone' => $phone,
            'name' => (string) $this->option('name'),
            'password' => $password,
        ];

        $validator = Validator::make($data, [
            'phone' => ['required', 'regex:/^\+84\d{9}$/'],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $adminRole = Role::query()->where('key', RoleKey::Admin->value)->first();

        if ($adminRole === null) {
            $this->error('Run RoleSeeder before creating an administrator.');

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'name' => $data['name'],
                'phone_verified_at' => now(),
                'password' => Hash::make((string) $password),
                'status' => UserStatus::Active,
            ],
        );
        $user->roles()->syncWithoutDetaching([
            $adminRole->id => ['granted_at' => now()],
        ]);

        $this->info("Admin ready: {$user->phone}");

        return self::SUCCESS;
    }
}
