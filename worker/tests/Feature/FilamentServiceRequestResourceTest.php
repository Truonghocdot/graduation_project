<?php

use App\Enums\PaymentMethod;
use App\Enums\RoleKey;
use App\Enums\ServiceRequestStatus;
use App\Filament\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\Payment;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

function actingAsServiceRequestAdmin(): User
{
    test()->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->create();
    $adminRoleId = Role::query()->where('key', RoleKey::Admin->value)->value('id');
    $admin->roles()->attach($adminRoleId, ['granted_at' => now()]);
    test()->actingAs($admin);

    return $admin;
}

test('lists and filters service requests in the Filament operations resource', function () {
    actingAsServiceRequestAdmin();
    $cashRequest = ServiceRequest::factory()->create([
        'status' => ServiceRequestStatus::SearchingDriver,
    ]);
    Payment::factory()->create([
        'service_request_id' => $cashRequest->id,
        'method' => PaymentMethod::Cash,
    ]);
    $walletRequest = ServiceRequest::factory()->create([
        'status' => ServiceRequestStatus::Scheduled,
        'scheduled_at' => now()->addHour(),
        'search_started_at' => null,
    ]);
    Payment::factory()->create([
        'service_request_id' => $walletRequest->id,
        'method' => PaymentMethod::Wallet,
    ]);

    Livewire::test(ListServiceRequests::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$cashRequest, $walletRequest])
        ->filterTable('status', ServiceRequestStatus::Scheduled->value)
        ->assertCanSeeTableRecords([$walletRequest])
        ->assertCanNotSeeTableRecords([$cashRequest]);
});

test('shows service request payment details and keeps the resource read only', function () {
    actingAsServiceRequestAdmin();
    $serviceRequest = ServiceRequest::factory()->create();
    Payment::factory()->create([
        'service_request_id' => $serviceRequest->id,
        'method' => PaymentMethod::Cash,
        'customer_payable' => 18_000,
    ]);

    $this->get(ServiceRequestResource::getUrl('view', ['record' => $serviceRequest]))
        ->assertOk()
        ->assertSee($serviceRequest->public_id)
        ->assertSee(PaymentMethod::Cash->getLabel());

    expect(ServiceRequestResource::canCreate())->toBeFalse()
        ->and(ServiceRequestResource::canEdit($serviceRequest))->toBeFalse()
        ->and(ServiceRequestResource::canDelete($serviceRequest))->toBeFalse();
});
