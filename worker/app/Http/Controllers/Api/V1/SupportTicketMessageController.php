<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Support\StoreTicketMessageRequest;
use App\Http\Resources\Api\V1\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportTicketService;

class SupportTicketMessageController extends Controller
{
    public function store(
        StoreTicketMessageRequest $request,
        SupportTicket $supportTicket,
        SupportTicketService $service,
    ): SupportTicketResource {
        $user = $request->user();
        assert($user instanceof User);

        return new SupportTicketResource($service->message(
            $user,
            $supportTicket,
            $request->string('body')->toString(),
            $request->file('attachment'),
        ));
    }
}
