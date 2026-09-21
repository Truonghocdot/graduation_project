<?php

use App\Enums\RoleKey;
use App\Enums\UserStatus;
use App\Filament\Pages\Auth\Login;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('redirects guests to the admin phone login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/login')->assertOk()->assertSee('Số điện thoại');
});

test('allows an active administrator to sign in with phone and password', function () {
    $admin = User::factory()->create([
        'phone' => '+84901234567',
        'password' => 'password123',
    ]);
    $adminRoleId = Role::query()->where('key', RoleKey::Admin->value)->value('id');
    $admin->roles()->attach($adminRoleId, ['granted_at' => now()]);

    Livewire::test(Login::class)
        ->fillForm([
            'phone' => '0901234567',
            'password' => 'password123',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($admin);
});

test('denies the panel to users without the admin role', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/driver-profiles')->assertForbidden();
});

test('creates or promotes an administrator through the command', function () {
    $exitCode = Artisan::call('app:create-admin-user', [
        'phone' => '0901234567',
        '--name' => 'System Admin',
        '--password' => 'password123',
    ]);

    expect($exitCode)->toBe(0);

    $admin = User::query()->where('phone', '+84901234567')->firstOrFail();
    expect($admin->status)->toBe(UserStatus::Active)
        ->and($admin->hasRole(RoleKey::Admin))->toBeTrue();
});
