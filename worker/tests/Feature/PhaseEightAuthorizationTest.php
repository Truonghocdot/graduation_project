<?php

use App\Enums\RoleKey;
use App\Enums\ServiceType;
use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\UserStatus;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Filament\Resources\Wallets\WalletResource;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportAdminService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ExecutionScenarioBuilder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function phaseEightStaff(RoleKey $role): User
{
    $user = User::factory()->create();
    $roleId = Role::query()->where('key', $role->value)->value('id');
    $user->roles()->attach($roleId, ['granted_at' => now()]);

    return $user;
}

test('allows support into support queue but denies finance resources', function () {
    $support = phaseEightStaff(RoleKey::Support);
    $this->actingAs($support);

    $this->get(SupportTicketResource::getUrl('index'))->assertOk();
    $this->get(WalletResource::getUrl('index'))->assertForbidden();
});

test('scopes support ticket details to queue or assigned staff', function () {
    $owner = User::factory()->create();
    $assigned = phaseEightStaff(RoleKey::Support);
    $otherSupport = phaseEightStaff(RoleKey::Support);
    $ticket = SupportTicket::query()->create([
        'opened_by' => $owner->id,
        'category' => 'OTHER',
        'priority' => SupportPriority::Normal,
        'status' => SupportTicketStatus::InReview,
        'subject' => 'Private assigned case',
        'description' => 'Only the assigned support user should see this.',
        'assigned_to' => $assigned->id,
        'version' => 1,
    ]);
    $this->actingAs($otherSupport);

    $this->get(SupportTicketResource::getUrl('view', ['record' => $ticket]))
        ->assertNotFound();
});

test('only an administrator can suspend a user and revokes access tokens', function () {
    $subject = User::factory()->create();
    $subject->createToken('test-device');
    $support = phaseEightStaff(RoleKey::Support);

    expect(fn () => app(SupportAdminService::class)->suspendUser(
        $subject,
        $support,
        'POLICY_VIOLATION',
    ))->toThrow(HttpException::class);

    $admin = phaseEightStaff(RoleKey::Admin);
    app(SupportAdminService::class)->suspendUser(
        $subject,
        $admin,
        'POLICY_VIOLATION',
    );

    expect($subject->fresh()?->status)->toBe(UserStatus::Suspended);
    $this->assertDatabaseCount('personal_access_tokens', 0);
    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $admin->id,
        'action' => 'USER_SUSPENDED',
        'reason_code' => 'POLICY_VIOLATION',
    ]);
});

test('limits realtime room access to the customer assigned driver and admin', function () {
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Drive);
    $url = '/api/v1/service-requests/'.$scenario['request']->public_id.'/realtime-access';

    Sanctum::actingAs($scenario['customer'], ['customer:*']);
    $this->getJson($url)->assertNoContent();

    Sanctum::actingAs($scenario['driver'], ['driver:*']);
    $this->getJson($url)->assertNoContent();

    Sanctum::actingAs(User::factory()->create(), ['customer:*']);
    $this->getJson($url)->assertNotFound();

    Sanctum::actingAs(phaseEightStaff(RoleKey::Admin), ['admin:*']);
    $this->getJson($url)->assertNoContent();
});
