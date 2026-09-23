<?php

use App\Enums\DriverReviewStatus;
use App\Enums\RoleKey;
use App\Filament\Resources\DriverProfiles\DriverProfileResource;
use App\Filament\Resources\DriverProfiles\Pages\ListDriverProfiles;
use App\Models\Role;
use App\Models\User;
use App\Models\VehicleType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleTypeSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\DriverApplicationBuilder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, VehicleTypeSeeder::class]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('lists driver applications for an administrator', function () {
    $admin = User::factory()->create();
    $adminRoleId = Role::query()->where('key', RoleKey::Admin->value)->value('id');
    $admin->roles()->attach($adminRoleId, ['granted_at' => now()]);
    $this->actingAs($admin);

    $profile = DriverApplicationBuilder::submitted(
        User::factory()->create(),
        VehicleType::query()->where('unique_key', 'MOTORBIKE')->firstOrFail(),
    );

    Livewire::test(ListDriverProfiles::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$profile])
        ->assertTableActionVisible('approve', $profile)
        ->assertTableActionVisible('reject', $profile)
        ->assertTableActionHidden('suspend', $profile);

    $this->get(DriverProfileResource::getUrl('view', ['record' => $profile]))
        ->assertOk();
});

test('approves a driver through the Filament table action and writes audit', function () {
    $admin = User::factory()->create();
    $adminRoleId = Role::query()->where('key', RoleKey::Admin->value)->value('id');
    $admin->roles()->attach($adminRoleId, ['granted_at' => now()]);
    $this->actingAs($admin);

    $profile = DriverApplicationBuilder::submitted(
        User::factory()->create(),
        VehicleType::query()->where('unique_key', 'MOTORBIKE')->firstOrFail(),
    );

    Livewire::test(ListDriverProfiles::class)
        ->callTableAction('approve', $profile, data: ['daily_cod_limit' => 8_000_000])
        ->assertHasNoTableActionErrors();

    expect($profile->fresh()->review_status)->toBe(DriverReviewStatus::Approved)
        ->and($profile->fresh()->cod_limit)->toBe(8_000_000.0);
    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $admin->id,
        'action' => 'DRIVER_APPROVED',
        'subject_id' => $profile->id,
    ]);
});

test('rejects a driver through the Filament table action with a reason', function () {
    $admin = User::factory()->create();
    $adminRoleId = Role::query()->where('key', RoleKey::Admin->value)->value('id');
    $admin->roles()->attach($adminRoleId, ['granted_at' => now()]);
    $this->actingAs($admin);

    $profile = DriverApplicationBuilder::submitted(
        User::factory()->create(),
        VehicleType::query()->where('unique_key', 'MOTORBIKE')->firstOrFail(),
    );

    Livewire::test(ListDriverProfiles::class)
        ->callTableAction('reject', $profile, data: ['reason_code' => 'DOCUMENT_UNREADABLE'])
        ->assertHasNoTableActionErrors();

    expect($profile->fresh()->review_status)->toBe(DriverReviewStatus::Rejected)
        ->and($profile->fresh()->review_reason_code)->toBe('DOCUMENT_UNREADABLE');
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'DRIVER_REJECTED',
        'reason_code' => 'DOCUMENT_UNREADABLE',
    ]);
});
