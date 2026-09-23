<?php

use App\Enums\PhoneVerificationPurpose;
use App\Enums\RoleKey;
use App\Enums\UserStatus;
use App\Models\PhoneVerification;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\PhoneVerificationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config()->set('otp.test_code', '123456');
    $this->seed(RoleSeeder::class);
});

test('registers a pending user and stores only the OTP hash', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Nguyen Van A',
        'phone' => '0901234567',
        'email' => 'customer@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.phone', '+84901234567')
        ->assertJsonPath('data.status', UserStatus::PendingVerification->value)
        ->assertJsonPath('meta.verification_required', true);

    $user = User::query()->where('phone', '+84901234567')->firstOrFail();
    $verification = PhoneVerification::query()->whereBelongsTo($user)->firstOrFail();

    expect($verification->code_hash)->not->toBe('123456')
        ->and(Hash::check('123456', $verification->code_hash))->toBeTrue();
});

test('returns Vietnamese validation messages for API form requests', function () {
    $this->postJson('/api/v1/auth/register', [
        'phone' => 'not-a-phone-number',
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Trường họ và tên là bắt buộc.')
        ->assertJsonPath('errors.phone.0', 'Số điện thoại phải là số điện thoại Việt Nam hợp lệ.')
        ->assertJsonPath('errors.email.0', 'Trường email phải là địa chỉ email hợp lệ.')
        ->assertJsonPath('errors.password.0', 'Xác nhận mật khẩu không khớp.');
});

test('verifies the phone and creates an authenticated customer session', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Nguyen Van A',
        'phone' => '0901234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $response = $this->postJson('/api/v1/auth/phone/verify', [
        'phone' => '0901234567',
        'code' => '123456',
        'device_id' => 'customer-device-1',
        'app_type' => 'CUSTOMER_APP',
        'platform' => 'ANDROID',
        'push_token' => 'push-token',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', UserStatus::Active->value)
        ->assertJsonPath('data.roles.0', RoleKey::Customer->value)
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonStructure(['token']);

    $user = User::query()->where('phone', '+84901234567')->firstOrFail();

    $this->assertDatabaseHas('user_devices', [
        'user_id' => $user->id,
        'device_id' => 'customer-device-1',
        'app_type' => 'CUSTOMER_APP',
    ]);
    expect($user->phone_verified_at)->not->toBeNull()
        ->and($user->tokens()->count())->toBe(1);
});

test('rejects an invalid verification code and persists the attempt count', function () {
    $user = User::factory()->unverified()->create();

    app(PhoneVerificationService::class)->issue(
        $user,
        PhoneVerificationPurpose::Register,
    );

    $this->postJson('/api/v1/auth/phone/verify', [
        'phone' => $user->phone,
        'code' => '000000',
        'device_id' => 'customer-device-1',
        'app_type' => 'CUSTOMER_APP',
        'platform' => 'ANDROID',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->assertDatabaseHas('phone_verifications', [
        'user_id' => $user->id,
        'attempt_count' => 1,
    ]);
});

test('resending verification invalidates the previous code record', function () {
    $user = User::factory()->unverified()->create();
    $service = app(PhoneVerificationService::class);
    $firstVerification = $service->issue($user, PhoneVerificationPurpose::Register);

    $this->postJson('/api/v1/auth/phone/resend', [
        'phone' => $user->phone,
    ])->assertAccepted();

    expect($firstVerification->fresh()->invalidated_at)->not->toBeNull()
        ->and(PhoneVerification::query()->whereBelongsTo($user)->count())->toBe(2);
});

test('logs in an active user with phone and password', function () {
    $user = User::factory()->create([
        'phone' => '+84901234567',
        'password' => 'password123',
    ]);
    $customerRoleId = Role::query()->where('key', RoleKey::Customer->value)->value('id');
    $user->roles()->attach($customerRoleId, ['granted_at' => now()]);

    $response = $this->postJson('/api/v1/auth/login', [
        'phone' => '0901234567',
        'password' => 'password123',
        'device_id' => 'customer-device-1',
        'app_type' => 'CUSTOMER_APP',
        'platform' => 'IOS',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $user->public_id)
        ->assertJsonStructure(['token']);
});

test('rejects login when the account is not active', function (UserStatus $status) {
    $user = User::factory()->create([
        'status' => $status,
        'password' => 'password123',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'phone' => $user->phone,
        'password' => 'password123',
        'device_id' => 'customer-device-1',
        'app_type' => 'CUSTOMER_APP',
        'platform' => 'ANDROID',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
})->with([
    'pending verification' => UserStatus::PendingVerification,
    'suspended' => UserStatus::Suspended,
    'closed' => UserStatus::Closed,
]);

test('returns the same forgot password response for known and unknown phones', function () {
    User::factory()->create(['phone' => '+84901234567']);

    $knownResponse = $this->postJson('/api/v1/auth/password/forgot', [
        'phone' => '0901234567',
    ]);
    $unknownResponse = $this->postJson('/api/v1/auth/password/forgot', [
        'phone' => '0909999999',
    ]);

    $knownResponse->assertAccepted();
    $unknownResponse->assertAccepted();
    expect($knownResponse->json())->toBe($unknownResponse->json());
});

test('resets the password and revokes existing access tokens', function () {
    $user = User::factory()->create([
        'phone' => '+84901234567',
        'password' => 'old-password',
    ]);
    $user->createToken('CUSTOMER_APP:old-device');

    $this->postJson('/api/v1/auth/password/forgot', [
        'phone' => '0901234567',
    ])->assertAccepted();

    $resetToken = $this->postJson('/api/v1/auth/password/verify', [
        'phone' => '0901234567',
        'code' => '123456',
    ])->assertOk()->json('reset_token');

    $this->postJson('/api/v1/auth/password/reset', [
        'phone' => '0901234567',
        'token' => $resetToken,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertNoContent();

    expect(Hash::check('new-password123', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);
});

test('logs out only the current bearer token', function () {
    $user = User::factory()->create();
    $currentToken = $user->createToken('CUSTOMER_APP:current-device')->plainTextToken;
    $user->createToken('CUSTOMER_APP:other-device');

    $this->withToken($currentToken)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    expect($user->tokens()->count())->toBe(1);
});

test('returns the authenticated user and rejects guests', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();

    $user = User::factory()->create();
    $token = $user->createToken('CUSTOMER_APP:customer-device-1')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->public_id)
        ->assertJsonPath('data.phone', $user->phone);
});
