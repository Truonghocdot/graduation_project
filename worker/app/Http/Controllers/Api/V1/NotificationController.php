<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        assert($user instanceof User);

        return NotificationResource::collection(
            $user->appNotifications()->latest()->paginate(30),
        );
    }

    public function read(
        Request $request,
        UserNotification $notification,
    ): JsonResponse {
        $user = $request->user();
        assert($user instanceof User);
        abort_unless($notification->user_id === $user->id, 404);
        $notification->forceFill([
            'read_at' => $notification->read_at ?? now(),
            'status' => 'READ',
        ])->save();

        return response()->json([
            'data' => (new NotificationResource($notification))->resolve($request),
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        return response()->json([
            'data' => [
                'unread_count' => $user->appNotifications()
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }
}
