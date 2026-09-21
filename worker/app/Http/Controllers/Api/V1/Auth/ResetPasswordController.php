<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Services\Auth\PhoneAuthService;
use Illuminate\Http\Response;

class ResetPasswordController extends Controller
{
    public function __invoke(ResetPasswordRequest $request, PhoneAuthService $auth): Response
    {
        $auth->resetPassword(
            $request->string('phone')->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        return response()->noContent();
    }
}
