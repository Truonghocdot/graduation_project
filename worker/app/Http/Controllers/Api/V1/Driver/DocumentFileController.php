<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\RoleKey;
use App\Http\Controllers\Controller;
use App\Models\DriverDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentFileController extends Controller
{
    public function __invoke(Request $request, DriverDocument $document): StreamedResponse
    {
        $user = $request->user();
        $isOwner = $document->driverProfile()->where('user_id', $user->id)->exists();

        abort_unless($isOwner || $user->hasRole(RoleKey::Admin), 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path);
    }
}
