<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleKey;
use App\Http\Controllers\Controller;
use App\Models\ServiceEvidence;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceEvidenceFileController extends Controller
{
    public function __invoke(
        Request $request,
        ServiceEvidence $evidence,
    ): StreamedResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $allowed = $evidence->serviceRequest->created_by === $user->id
            || $evidence->assignment->driverProfile->user_id === $user->id
            || $user->hasRole(RoleKey::Admin);
        abort_unless($allowed, 404);
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        return $disk->download(
            $evidence->storage_path,
            $evidence->original_name,
            ['Content-Type' => $evidence->mime_type],
        );
    }
}
