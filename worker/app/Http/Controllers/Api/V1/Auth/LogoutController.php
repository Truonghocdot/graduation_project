<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($user !== null && $token instanceof PersonalAccessToken) {
            [$appType, $deviceId] = array_pad(explode(':', $token->name, 2), 2, null);

            if ($deviceId !== null) {
                $user->devices()
                    ->where('device_id', $deviceId)
                    ->where('app_type', $appType)
                    ->update(['revoked_at' => now()]);
            }

            $token->delete();
        }

        return response()->noContent();
    }
}
