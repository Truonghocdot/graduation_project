<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Support\StoreSupportTicketRequest;
use App\Http\Resources\Api\V1\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupportTicketController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        assert($user instanceof User);

        return SupportTicketResource::collection(
            SupportTicket::query()
                ->where('opened_by', $user->id)
                ->with(['serviceRequest', 'assignee'])
                ->latest()
                ->paginate(20),
        );
    }

    public function store(
        StoreSupportTicketRequest $request,
        SupportTicketService $service,
    ): SupportTicketResource {
        $user = $request->user();
        assert($user instanceof User);

        return new SupportTicketResource($service->create(
            $user,
            $request->safe()->except('attachment'),
            $request->file('attachment'),
            $request->idempotencyKey(),
        ));
    }

    public function show(
        Request $request,
        SupportTicket $supportTicket,
        SupportTicketService $service,
    ): SupportTicketResource {
        $user = $request->user();
        assert($user instanceof User);
        $service->authorize($user, $supportTicket);

        return new SupportTicketResource($supportTicket->load([
            'serviceRequest',
            'assignee',
            'messages.sender',
            'attachments.uploader',
        ]));
    }
}
