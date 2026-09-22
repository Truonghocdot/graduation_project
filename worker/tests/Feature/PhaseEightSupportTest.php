<?php

use App\Enums\AssignmentStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ExecutionScenarioBuilder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('allows participants to rate a completed service once per direction', function () {
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Drive);
    $scenario['request']->forceFill(['status' => ServiceRequestStatus::Completed])->save();
    $scenario['assignment']->forceFill(['status' => AssignmentStatus::Completed])->save();
    Sanctum::actingAs($scenario['customer'], ['customer:*']);
    $url = '/api/v1/service-requests/'.$scenario['request']->public_id.'/ratings';

    $this->postJson($url, [
        'score' => 2,
        'tags' => ['LATE'],
        'comment' => 'Driver arrived late.',
    ])->assertCreated()
        ->assertJsonPath('data.score', 2)
        ->assertJsonPath('data.moderation_status', 'FLAGGED');

    $this->postJson($url, ['score' => 5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rating');
    $this->assertDatabaseCount('ratings', 1);
    $this->assertDatabaseHas('outbox_events', ['event_type' => 'LOW_RATING_FLAGGED']);
});

test('does not allow an unrelated user to rate a service', function () {
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Drive);
    $scenario['request']->forceFill(['status' => ServiceRequestStatus::Completed])->save();
    Sanctum::actingAs(User::factory()->create(), ['customer:*']);

    $this->postJson(
        '/api/v1/service-requests/'.$scenario['request']->public_id.'/ratings',
        ['score' => 5],
    )->assertNotFound();
});

test('creates one support ticket with a private attachment on idempotent retry', function () {
    Storage::fake('local');
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Delivery);
    Sanctum::actingAs($scenario['customer'], ['customer:*']);
    $payload = [
        'service_request_id' => $scenario['request']->public_id,
        'category' => 'DAMAGE',
        'subject' => 'Damaged parcel',
        'description' => 'The parcel was damaged during delivery.',
        'attachment' => UploadedFile::fake()->image('damage.jpg'),
    ];
    $headers = [
        'Accept' => 'application/json',
        'Idempotency-Key' => 'ticket-create-key',
    ];

    $first = $this->post('/api/v1/support/tickets', $payload, $headers)
        ->assertCreated();
    $second = $this->post('/api/v1/support/tickets', [
        ...$payload,
        'attachment' => UploadedFile::fake()->image('damage-retry.jpg'),
    ], $headers)->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'));
    $this->assertDatabaseCount('support_tickets', 1);
    $this->assertDatabaseCount('ticket_attachments', 1);
    $attachmentId = $first->json('data.attachments.0.id');
    $this->get("/api/v1/support/attachments/{$attachmentId}/file")->assertOk();

    Sanctum::actingAs(User::factory()->create(), ['customer:*']);
    $this->get("/api/v1/support/attachments/{$attachmentId}/file")->assertNotFound();
});

test('reports an SOS incident over HTTPS idempotently', function () {
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Drive);
    Sanctum::actingAs($scenario['customer'], ['customer:*']);
    $url = '/api/v1/service-requests/'.$scenario['request']->public_id.'/incidents';
    $payload = [
        'incident_type' => 'SOS',
        'description' => 'Immediate safety assistance required.',
        'latitude' => 10.77,
        'longitude' => 106.68,
    ];
    $headers = ['Idempotency-Key' => 'sos-key'];

    $first = $this->postJson($url, $payload, $headers)->assertCreated();
    $second = $this->postJson($url, $payload, $headers)->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and($first->json('data.severity'))->toBe('CRITICAL');
    $this->assertDatabaseCount('incidents', 1);
    $this->assertDatabaseHas('outbox_events', [
        'event_type' => 'SAFETY_INCIDENT_REPORTED',
    ]);
});
