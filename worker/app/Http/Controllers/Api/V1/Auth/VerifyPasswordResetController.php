<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\VerifyPasswordResetRequest;
use App\Services\Auth\PhoneAuthService;
use Illuminate\Http\JsonResponse;

class VerifyPasswordResetController extends Controller
{
    public function __invoke(VerifyPasswordResetRequest $request, PhoneAuthService $auth): JsonResponse
    {
        $token = $auth->issuePasswordResetToken(
            $request->string('phone')->toString(),
            $request->string('code')->toString(),
        );

        return response()->json([
            'reset_token' => $token,
            'expires_in' => config('otp.reset_token_expires_seconds'),
        ]);
    }
}
