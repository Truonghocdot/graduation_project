<?php

use App\Enums\IncidentStatus;
use App\Enums\RatingModerationStatus;
use App\Enums\RoleKey;
use App\Enums\ServiceType;
use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\UserStatus;
use App\Filament\Resources\Incidents\Pages\ListIncidents;
use App\Filament\Resources\Ratings\Pages\ListRatings;
use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Incident;
use App\Models\Rating;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\ExecutionScenarioBuilder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function supportOperationsStaff(RoleKey $role): User
{
    $user = User::factory()->create();
    $roleId = Role::query()->where('key', $role->value)->value('id');
    $user->roles()->attach($roleId, ['granted_at' => now()]);

    return $user;
}

test('support claims and resolves a ticket with audit', function () {
    $support = supportOperationsStaff(RoleKey::Support);
    $this->actingAs($support);
    $ticket = SupportTicket::query()->create([
        'opened_by' => User::factory()->create()->id,
        'category' => 'PAYMENT',
        'priority' => SupportPriority::High,
        'status' => SupportTicketStatus::Open,
        'subject' => 'Payment issue',
        'description' => 'Payment does not match receipt.',
        'version' => 1,
    ]);

    Livewire::test(ListSupportTickets::class)
        ->callTableAction('claim', $ticket)
        ->assertHasNoTableActionErrors()
        ->callTableAction('resolve', $ticket, data: [
            'resolution_code' => 'EXPLAINED',
            'resolution_note' => 'Settlement and receipt were reconciled.',
        ])
        ->assertHasNoTableActionErrors();

    expect($ticket->fresh()?->status)->toBe(SupportTicketStatus::Resolved)
        ->and($ticket->fresh()?->assigned_to)->toBe($support->id);
    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $support->id,
        'action' => 'SUPPORT_TICKET_RESOLVED',
    ]);
});

test('support resolves an incident and moderates a rating', function () {
    $support = supportOperationsStaff(RoleKey::Support);
    $this->actingAs($support);
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Drive);
    $incident = Incident::query()->create([
        'service_request_id' => $scenario['request']->id,
        'reported_by' => $scenario['customer']->id,
        'incident_type' => 'SAFETY',
        'severity' => 'HIGH',
        'status' => IncidentStatus::Open,
        'description' => 'Safety review required.',
    ]);
    $rating = Rating::query()->create([
        'service_request_id' => $scenario['request']->id,
        'assignment_id' => $scenario['assignment']->id,
        'reviewer_user_id' => $scenario['customer']->id,
        'reviewee_user_id' => $scenario['driver']->id,
        'direction' => 'CUSTOMER_TO_DRIVER',
        'score' => 1,
        'moderation_status' => RatingModerationStatus::Flagged,
    ]);

    Livewire::test(ListIncidents::class)
        ->callTableAction('resolve', $incident, data: [
            'resolution_code' => 'SAFETY_CONFIRMED',
        ])
        ->assertHasNoTableActionErrors();
    Livewire::test(ListRatings::class)
        ->callTableAction('moderate', $rating, data: [
            'status' => RatingModerationStatus::Hidden->value,
            'reason_code' => 'ABUSIVE_CONTENT',
        ])
        ->assertHasNoTableActionErrors();

    expect($incident->fresh()?->status)->toBe(IncidentStatus::Resolved)
        ->and($rating->fresh()?->moderation_status)->toBe(RatingModerationStatus::Hidden);
});

test('administrator suspends a user through Filament', function () {
    $admin = supportOperationsStaff(RoleKey::Admin);
    $this->actingAs($admin);
    $subject = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callTableAction('suspend', $subject, data: [
            'reason_code' => 'VERIFIED_FRAUD',
        ])
        ->assertHasNoTableActionErrors();

    expect($subject->fresh()?->status)->toBe(UserStatus::Suspended);
});
