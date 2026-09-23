<?php

use App\Enums\DriverDocumentType;
use App\Enums\DriverReviewStatus;
use App\Models\DriverDocument;
use App\Models\User;
use App\Models\VehicleType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleTypeSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed([RoleSeeder::class, VehicleTypeSeeder::class]);
});

test('submits a complete driver application with private documents', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['customer:*']);

    $this->postJson('/api/v1/driver/application')->assertCreated()
        ->assertJsonPath('data.review_status', DriverReviewStatus::Draft->value);

    $vehicleTypeId = VehicleType::query()->where('unique_key', 'MOTORBIKE')->value('public_id');
    $vehicleId = $this->postJson('/api/v1/driver/vehicles', [
        'vehicle_type_id' => $vehicleTypeId,
        'plate_number' => '59 a1 12345',
        'brand' => 'Honda',
        'model' => 'Wave',
        'color' => 'Black',
    ])->assertCreated()
        ->assertJsonPath('data.plate_number', '59A112345')
        ->json('data.id');

    foreach (DriverDocumentType::cases() as $documentType) {
        $payload = [
            'document_type' => $documentType->value,
            'file' => UploadedFile::fake()->image($documentType->value.'.jpg'),
            'expires_at' => now()->addYear()->toDateString(),
        ];

        if ($documentType->belongsToVehicle()) {
            $payload['vehicle_id'] = $vehicleId;
        }

        if (in_array($documentType, [
            DriverDocumentType::Identity,
            DriverDocumentType::DriverLicense,
            DriverDocumentType::VehicleRegistration,
        ], true)) {
            $payload['document_number'] = $documentType->value.'-123';
        }

        $this->post('/api/v1/driver/documents', $payload, ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', $documentType->value);
    }

    $this->postJson('/api/v1/driver/application/submit', [
        'vehicle_id' => $vehicleId,
        'service_types' => ['DELIVERY', 'DRIVE'],
    ])->assertOk()
        ->assertJsonPath('data.review_status', DriverReviewStatus::PendingReview->value)
        ->assertJsonCount(2, 'data.capabilities');

    $this->assertDatabaseHas('driver_profiles', [
        'user_id' => $user->id,
        'review_status' => DriverReviewStatus::PendingReview->value,
    ]);
    $this->assertDatabaseCount('driver_documents', count(DriverDocumentType::cases()));

    DriverDocument::query()->each(
        fn (DriverDocument $document) => Storage::disk('local')->assertExists($document->file_path),
    );
});

test('rejects submission when required documents are missing', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['customer:*']);
    $this->postJson('/api/v1/driver/application')->assertCreated();

    $vehicleTypeId = VehicleType::query()->where('unique_key', 'MOTORBIKE')->value('public_id');
    $vehicleId = $this->postJson('/api/v1/driver/vehicles', [
        'vehicle_type_id' => $vehicleTypeId,
        'plate_number' => '59A199999',
    ])->assertCreated()->json('data.id');

    $this->postJson('/api/v1/driver/application/submit', [
        'vehicle_id' => $vehicleId,
        'service_types' => ['DELIVERY'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('documents');
});

test('replaces a document when its owner uploads the same type again', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['customer:*']);
    $this->postJson('/api/v1/driver/application')->assertCreated();

    $this->post('/api/v1/driver/documents', [
        'document_type' => DriverDocumentType::Identity->value,
        'document_number' => '038205007820',
        'file' => UploadedFile::fake()->image('identity-first.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();
    $firstPath = DriverDocument::query()->sole()->file_path;

    $this->post('/api/v1/driver/documents', [
        'document_type' => DriverDocumentType::Identity->value,
        'document_number' => '038205007820',
        'file' => UploadedFile::fake()->image('identity-replacement.jpg'),
    ], ['Accept' => 'application/json'])->assertOk();

    $document = DriverDocument::query()->sole();
    expect($document->document_number)->toBe('038205007820')
        ->and($document->file_path)->not->toBe($firstPath);
    Storage::disk('local')->assertMissing($firstPath);
});

test('rejects a document number already used by another active driver profile', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    Sanctum::actingAs($owner, ['customer:*']);
    $this->postJson('/api/v1/driver/application')->assertCreated();
    $this->post('/api/v1/driver/documents', [
        'document_type' => DriverDocumentType::Identity->value,
        'document_number' => '038205007820',
        'file' => UploadedFile::fake()->image('owner-identity.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();

    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser, ['customer:*']);
    $this->postJson('/api/v1/driver/application')->assertCreated();

    $this->post('/api/v1/driver/documents', [
        'document_type' => DriverDocumentType::Identity->value,
        'document_number' => '038205007820',
        'file' => UploadedFile::fake()->image('other-identity.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('document_number');

    $this->assertDatabaseCount('driver_documents', 1);
});

test('prevents another user from deleting a driver document', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    Sanctum::actingAs($owner, ['customer:*']);
    $this->postJson('/api/v1/driver/application')->assertCreated();
    $documentId = $this->post('/api/v1/driver/documents', [
        'document_type' => DriverDocumentType::Portrait->value,
        'file' => UploadedFile::fake()->image('portrait.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

    $this->get("/api/v1/driver/documents/{$documentId}/file")
        ->assertOk();

    Sanctum::actingAs(User::factory()->create(), ['customer:*']);

    $this->deleteJson("/api/v1/driver/documents/{$documentId}")
        ->assertNotFound();
    $this->get("/api/v1/driver/documents/{$documentId}/file")
        ->assertNotFound();
});

test('allows only one vehicle during driver onboarding', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['customer:*']);
    $this->postJson('/api/v1/driver/application')->assertCreated();
    $vehicleTypeId = VehicleType::query()->where('unique_key', 'MOTORBIKE')->value('public_id');

    $this->postJson('/api/v1/driver/vehicles', [
        'vehicle_type_id' => $vehicleTypeId,
        'plate_number' => '59A112345',
    ])->assertCreated();
    $this->postJson('/api/v1/driver/vehicles', [
        'vehicle_type_id' => $vehicleTypeId,
        'plate_number' => '59A167890',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('vehicle_type_id');
});
