<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssignmentStatus;
use App\Enums\RoleKey;
use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RealtimeAccessController extends Controller
{
    public function __invoke(
        ServiceRequest $serviceRequest,
        Request $request,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $assignedDriver = $serviceRequest->assignments()
            ->where('status', AssignmentStatus::Active->value)
            ->whereHas(
                'driverProfile',
                fn ($query) => $query->where('user_id', $user->id),
            )
            ->exists();

        abort_unless(
            $serviceRequest->created_by === $user->id
                || $assignedDriver
                || $user->hasRole(RoleKey::Admin),
            404,
        );

        return response()->noContent();
    }
}
