<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\PhoneAuthService;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, PhoneAuthService $auth): JsonResponse
    {
        $result = $auth->login(
            $request->string('phone')->toString(),
            $request->string('password')->toString(),
            $request->deviceContext(),
        );

        return response()->json([
            'data' => UserResource::make($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ]);
    }
}
