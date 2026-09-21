<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ResendPhoneVerificationRequest;
use App\Services\Auth\PhoneAuthService;
use Illuminate\Http\JsonResponse;

class ResendPhoneVerificationController extends Controller
{
    public function __invoke(
        ResendPhoneVerificationRequest $request,
        PhoneAuthService $auth,
    ): JsonResponse {
        $auth->resendRegistrationCode($request->string('phone')->toString());

        return response()->json([
            'message' => 'If the phone number is pending verification, a new code has been issued.',
        ], 202);
    }
}
