<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\Support\SupportTicketService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentFileController extends Controller
{
    public function __invoke(
        Request $request,
        TicketAttachment $attachment,
        SupportTicketService $tickets,
    ): StreamedResponse {
        $user = $request->user();
        assert($user instanceof User);
        $tickets->authorize($user, $attachment->ticket);
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        return $disk->download(
            $attachment->storage_path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type],
        );
    }
}
