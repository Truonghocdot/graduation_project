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
use App\Http\Controllers\Api\V1\Driver\ApplicationController as DriverApplicationController;
use App\Http\Controllers\Api\V1\Driver\ApplicationSubmissionController;
use App\Http\Controllers\Api\V1\Driver\AvailabilityController;
use App\Http\Controllers\Api\V1\Driver\DocumentController;
use App\Http\Controllers\Api\V1\Driver\DocumentFileController;
use App\Http\Controllers\Api\V1\Driver\SelectedVehicleController;
use App\Http\Controllers\Api\V1\Driver\VehicleController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
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
            Route::put('driver/availability/online', [AvailabilityController::class, 'online']);
            Route::put('driver/availability/offline', [AvailabilityController::class, 'offline']);
        });

    });
});
