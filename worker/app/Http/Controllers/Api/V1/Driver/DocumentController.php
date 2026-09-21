<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\StoreDriverDocumentRequest;
use App\Http\Resources\Api\V1\DriverDocumentResource;
use App\Models\DriverDocument;
use App\Services\Driver\DriverOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

class DocumentController extends Controller
{
    public function store(
        StoreDriverDocumentRequest $request,
        DriverOnboardingService $onboarding,
    ): DriverDocumentResource {
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        return DriverDocumentResource::make($onboarding->addDocument(
            $request->user(),
            $request->documentAttributes(),
            $file,
        ));
    }

    public function destroy(
        Request $request,
        DriverDocument $document,
        DriverOnboardingService $onboarding,
    ): Response {
        $onboarding->removeDocument($request->user(), $document);

        return response()->noContent();
    }
}
