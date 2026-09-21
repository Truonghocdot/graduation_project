<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\VerifyPhoneRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\PhoneAuthService;
use Illuminate\Http\JsonResponse;

class VerifyPhoneController extends Controller
{
    public function __invoke(VerifyPhoneRequest $request, PhoneAuthService $auth): JsonResponse
    {
        $result = $auth->verifyRegistration(
            $request->string('phone')->toString(),
            $request->string('code')->toString(),
            $request->deviceContext(),
        );

        return response()->json([
            'data' => UserResource::make($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ]);
    }
}
