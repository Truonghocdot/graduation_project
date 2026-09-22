<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Chat\StoreChatMessageRequest;
use App\Http\Resources\Api\V1\ChatMessageResource;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChatController extends Controller
{
    public function index(
        Request $request,
        ServiceRequest $serviceRequest,
        ChatService $chat,
    ): AnonymousResourceCollection {
        $user = $request->user();
        assert($user instanceof User);

        return ChatMessageResource::collection($chat->messages($user, $serviceRequest));
    }

    public function store(
        StoreChatMessageRequest $request,
        ServiceRequest $serviceRequest,
        ChatService $chat,
    ): ChatMessageResource {
        $user = $request->user();
        assert($user instanceof User);

        return new ChatMessageResource($chat->send(
            $user,
            $serviceRequest,
            $request->string('client_message_id')->toString(),
            $request->string('body')->toString(),
        ));
    }

    public function unread(Request $request, ChatService $chat): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        return response()->json(['data' => ['unread_count' => $chat->unreadCount($user)]]);
    }
}
