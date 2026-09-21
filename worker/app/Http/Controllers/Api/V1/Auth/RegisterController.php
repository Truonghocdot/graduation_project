<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\PhoneAuthService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, PhoneAuthService $auth): JsonResponse
    {
        $result = $auth->register($request->registrationAttributes());

        return response()->json([
            'data' => UserResource::make($result['user']),
            'meta' => [
                'verification_required' => true,
                'expires_at' => $result['expires_at'],
            ],
        ], 201);
    }
}
