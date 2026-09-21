<?php

use App\Enums\AssignmentStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\RoleKey;
use App\Enums\ServiceRequestStatus;
use App\Filament\Pages\MatchingMonitor;
use App\Filament\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Models\Assignment;
use App\Models\DriverProfile;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

function actingAsMatchingAdmin(): User
{
    test()->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->create();
    $roleId = Role::query()->where('key', RoleKey::Admin->value)->value('id');
    $admin->roles()->attach($roleId, ['granted_at' => now()]);
    test()->actingAs($admin);

    return $admin;
}

test('renders searchable and assigned requests on the matching monitor', function () {
    actingAsMatchingAdmin();
    $request = ServiceRequest::factory()->create([
        'status' => ServiceRequestStatus::SearchingDriver,
    ]);

    $this->get(MatchingMonitor::getUrl())
        ->assertOk()
        ->assertSee($request->public_id);
});

test('restarts matching through a Filament action with audit', function () {
    $admin = actingAsMatchingAdmin();
    $request = ServiceRequest::factory()->create([
        'status' => ServiceRequestStatus::Assigned,
    ]);
    $profile = DriverProfile::factory()->create([
        'availability_status' => DriverAvailabilityStatus::Busy,
    ]);
    $vehicle = Vehicle::factory()->create([
        'driver_profile_id' => $profile->id,
        'vehicle_type_id' => $request->vehicle_type_id,
        'is_selected' => true,
    ]);
    $assignment = Assignment::factory()->create([
        'service_request_id' => $request->id,
        'driver_profile_id' => $profile->id,
        'vehicle_id' => $vehicle->id,
        'status' => AssignmentStatus::Active,
    ]);

    Livewire::test(ListServiceRequests::class)
        ->callTableAction('restartMatching', $request, data: ['reason_code' => 'DRIVER_NO_RESPONSE'])
        ->assertHasNoTableActionErrors();

    expect($request->fresh()?->status)->toBe(ServiceRequestStatus::SearchingDriver)
        ->and($assignment->fresh()?->status)->toBe(AssignmentStatus::Cancelled)
        ->and($profile->fresh()?->availability_status)->toBe(DriverAvailabilityStatus::Online);
    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $admin->id,
        'action' => 'MATCHING_RESTARTED',
        'reason_code' => 'DRIVER_NO_RESPONSE',
    ]);
});

test('cancels an active matching request through Filament with audit', function () {
    $admin = actingAsMatchingAdmin();
    $request = ServiceRequest::factory()->create([
        'status' => ServiceRequestStatus::SearchingDriver,
    ]);

    Livewire::test(ListServiceRequests::class)
        ->callTableAction('cancel', $request, data: ['reason_code' => 'ADMIN_OPERATIONAL_CANCEL'])
        ->assertHasNoTableActionErrors();

    expect($request->fresh()?->status)->toBe(ServiceRequestStatus::Cancelled);
    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $admin->id,
        'action' => 'SERVICE_REQUEST_CANCELLED',
        'reason_code' => 'ADMIN_OPERATIONAL_CANCEL',
    ]);
});
