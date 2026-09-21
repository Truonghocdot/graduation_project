<?php

use App\Enums\RoleKey;
use App\Filament\Resources\VehicleTypes\Pages\CreateVehicleType;
use App\Filament\Resources\VehicleTypes\Pages\EditVehicleType;
use App\Filament\Resources\VehicleTypes\Pages\ListVehicleTypes;
use App\Models\Role;
use App\Models\User;
use App\Models\VehicleType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleTypeSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, VehicleTypeSeeder::class]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = User::factory()->create();
    $adminRoleId = Role::query()->where('key', RoleKey::Admin->value)->value('id');
    $admin->roles()->attach($adminRoleId, ['granted_at' => now()]);
    $this->actingAs($admin);
});

test('lists seeded vehicle types', function () {
    $vehicleTypes = VehicleType::query()->get();

    Livewire::test(ListVehicleTypes::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($vehicleTypes);
});

test('creates a vehicle type through Filament', function () {
    Livewire::test(CreateVehicleType::class)
        ->fillForm([
            'unique_key' => 'CAR_7_SEAT',
            'name' => 'Ô tô 7 chỗ',
            'passenger_capacity' => 7,
            'max_weight_kg' => 150,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('vehicle_types', [
        'unique_key' => 'CAR_7_SEAT',
        'name' => 'Ô tô 7 chỗ',
        'passenger_capacity' => 7,
    ]);
});

test('updates a vehicle type through Filament', function () {
    $vehicleType = VehicleType::query()->where('unique_key', 'MOTORBIKE')->firstOrFail();

    Livewire::test(EditVehicleType::class, ['record' => $vehicleType->getRouteKey()])
        ->fillForm(['name' => 'Xe máy giao hàng'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('vehicle_types', [
        'id' => $vehicleType->id,
        'name' => 'Xe máy giao hàng',
    ]);
});
