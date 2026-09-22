<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChatMessage */
class ChatMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'client_message_id' => $this->client_message_id,
            'sender' => $this->whenLoaded('sender', fn () => [
                'id' => $this->sender->public_id,
                'name' => $this->sender->name,
            ]),
            'message_type' => $this->message_type,
            'body' => $this->body,
            'sent_at' => $this->sent_at,
            'read_at' => $this->read_at,
        ];
    }
}
