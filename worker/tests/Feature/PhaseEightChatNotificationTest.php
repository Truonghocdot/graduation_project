<?php

use App\Enums\ServiceType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ExecutionScenarioBuilder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('persists an idempotent chat message and tracks unread counts', function () {
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Drive);
    $url = '/api/v1/service-requests/'.$scenario['request']->public_id.'/chat';
    $messageId = (string) Str::uuid();
    Sanctum::actingAs($scenario['customer'], ['customer:*']);

    $first = $this->postJson($url, [
        'client_message_id' => $messageId,
        'body' => 'I am waiting at the pickup point.',
    ])->assertCreated();
    $second = $this->postJson($url, [
        'client_message_id' => $messageId,
        'body' => 'I am waiting at the pickup point.',
    ])->assertOk();

    expect($second->json('data.id'))->toBe($first->json('data.id'));
    $this->assertDatabaseCount('chat_messages', 1);
    $this->assertDatabaseCount('notifications', 1);

    Sanctum::actingAs($scenario['driver'], ['driver:*']);
    $this->getJson('/api/v1/chat/unread')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 1);
    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.0.body', 'I am waiting at the pickup point.');
    $this->getJson('/api/v1/chat/unread')
        ->assertJsonPath('data.unread_count', 0);
});

test('does not expose chat to an unrelated user', function () {
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Delivery);
    Sanctum::actingAs(User::factory()->create(), ['customer:*']);

    $this->getJson(
        '/api/v1/service-requests/'.$scenario['request']->public_id.'/chat',
    )->assertNotFound();
});

test('lists and marks only the current user notification as read', function () {
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Drive);
    $url = '/api/v1/service-requests/'.$scenario['request']->public_id.'/chat';
    Sanctum::actingAs($scenario['customer'], ['customer:*']);
    $this->postJson($url, [
        'client_message_id' => (string) Str::uuid(),
        'body' => 'Hello driver.',
    ])->assertCreated();
    Sanctum::actingAs($scenario['driver'], ['driver:*']);

    $notificationId = $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'CHAT_MESSAGE_RECEIVED')
        ->json('data.0.id');
    $this->getJson('/api/v1/notifications/unread')
        ->assertJsonPath('data.unread_count', 1);
    $this->putJson("/api/v1/notifications/{$notificationId}/read")
        ->assertOk()
        ->assertJsonPath('data.status', 'READ');

    Sanctum::actingAs(User::factory()->create(), ['customer:*']);
    $this->putJson("/api/v1/notifications/{$notificationId}/read")
        ->assertNotFound();
});
