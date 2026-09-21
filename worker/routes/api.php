<?php

use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResendPhoneVerificationController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\VerifyPasswordResetController;
use App\Http\Controllers\Api\V1\Auth\VerifyPhoneController;
use App\Http\Controllers\Api\V1\Catalog\VehicleTypeController;
use App\Http\Controllers\Api\V1\DeliveryOrderController;
use App\Http\Controllers\Api\V1\Driver\ApplicationController as DriverApplicationController;
use App\Http\Controllers\Api\V1\Driver\ApplicationSubmissionController;
use App\Http\Controllers\Api\V1\Driver\AvailabilityController;
use App\Http\Controllers\Api\V1\Driver\BankAccountController;
use App\Http\Controllers\Api\V1\Driver\DocumentController;
use App\Http\Controllers\Api\V1\Driver\DocumentFileController;
use App\Http\Controllers\Api\V1\Driver\LocationController;
use App\Http\Controllers\Api\V1\Driver\OfferController;
use App\Http\Controllers\Api\V1\Driver\OfferResponseController;
use App\Http\Controllers\Api\V1\Driver\SelectedVehicleController;
use App\Http\Controllers\Api\V1\Driver\ServiceEvidenceController;
use App\Http\Controllers\Api\V1\Driver\ServiceExecutionController;
use App\Http\Controllers\Api\V1\Driver\VehicleController;
use App\Http\Controllers\Api\V1\Driver\WithdrawalController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\RideBookingController;
use App\Http\Controllers\Api\V1\SePayWebhookController;
use App\Http\Controllers\Api\V1\ServiceEvidenceFileController;
use App\Http\Controllers\Api\V1\ServiceRequestCancellationController;
use App\Http\Controllers\Api\V1\ServiceRequestSnapshotController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WalletTopupController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('webhooks/sepay', SePayWebhookController::class);

    Route::prefix('auth')->group(function (): void {
        Route::post('register', RegisterController::class)
            ->middleware('throttle:auth-otp');
        Route::post('phone/verify', VerifyPhoneController::class)
            ->middleware('throttle:auth-otp');
        Route::post('phone/resend', ResendPhoneVerificationController::class)
            ->middleware('throttle:auth-otp');
        Route::post('login', LoginController::class)
            ->middleware('throttle:auth-login');
        Route::post('password/forgot', ForgotPasswordController::class)
            ->middleware('throttle:auth-otp');
        Route::post('password/verify', VerifyPasswordResetController::class)
            ->middleware('throttle:auth-otp');
        Route::post('password/reset', ResetPasswordController::class)
            ->middleware('throttle:auth-otp');

        Route::post('logout', LogoutController::class)
            ->middleware('auth:sanctum');
    });

    Route::get('me', MeController::class)
        ->middleware('auth:sanctum');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('catalog/vehicle-types', VehicleTypeController::class);
        Route::get('wallet', [WalletController::class, 'show']);
        Route::get('wallet/topups', [WalletTopupController::class, 'index']);
        Route::post('wallet/topups', [WalletTopupController::class, 'store']);
        Route::post('quotes', QuoteController::class)
            ->middleware('throttle:quotes');
        Route::post('delivery/orders', [DeliveryOrderController::class, 'store']);
        Route::post('rides/bookings', [RideBookingController::class, 'store']);
        Route::post('service-requests/{serviceRequest}/cancel', ServiceRequestCancellationController::class);
        Route::get('service-requests/{serviceRequest}', ServiceRequestSnapshotController::class);
        Route::get('service-evidence/{evidence}/file', ServiceEvidenceFileController::class)
            ->name('api.v1.service-evidence.file');

        Route::get('driver/application', [DriverApplicationController::class, 'show']);
        Route::post('driver/application', [DriverApplicationController::class, 'store']);
        Route::post('driver/application/submit', ApplicationSubmissionController::class);
        Route::post('driver/documents', [DocumentController::class, 'store']);
        Route::delete('driver/documents/{document}', [DocumentController::class, 'destroy']);
        Route::get('driver/documents/{document}/file', DocumentFileController::class)
            ->name('api.v1.driver-documents.file');
        Route::post('driver/vehicles', [VehicleController::class, 'store']);
        Route::patch('driver/vehicles/{vehicle}', [VehicleController::class, 'update']);
        Route::put('driver/vehicles/{vehicle}/selected', SelectedVehicleController::class);

        Route::middleware('role:DRIVER')->group(function (): void {
            Route::get('driver/bank-accounts', [BankAccountController::class, 'index']);
            Route::post('driver/bank-accounts', [BankAccountController::class, 'store']);
            Route::get('driver/offers', [OfferController::class, 'index']);
            Route::post('driver/offers/{driverOffer}/respond', OfferResponseController::class);
            Route::put('driver/location', LocationController::class);
            Route::post('driver/service-requests/{serviceRequest}/evidence', [ServiceEvidenceController::class, 'store']);
            Route::post('driver/service-requests/{serviceRequest}/transition', [ServiceExecutionController::class, 'update']);
            Route::get('driver/withdrawals', [WithdrawalController::class, 'index']);
            Route::post('driver/withdrawals', [WithdrawalController::class, 'store']);
            Route::put('driver/availability/online', [AvailabilityController::class, 'online']);
            Route::put('driver/availability/offline', [AvailabilityController::class, 'offline']);
        });

    });
});
