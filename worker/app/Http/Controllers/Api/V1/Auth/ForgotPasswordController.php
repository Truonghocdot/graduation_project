<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Services\Auth\PhoneAuthService;
use Illuminate\Http\JsonResponse;

class ForgotPasswordController extends Controller
{
    public function __invoke(ForgotPasswordRequest $request, PhoneAuthService $auth): JsonResponse
    {
        $auth->startPasswordReset($request->string('phone')->toString());

        return response()->json([
            'message' => 'If the phone number is active, a verification code has been issued.',
        ], 202);
    }
}
