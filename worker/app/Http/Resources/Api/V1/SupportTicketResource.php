<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupportTicket */
class SupportTicketResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'service_request_id' => $this->serviceRequest?->public_id,
            'category' => $this->category,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'subject' => $this->subject,
            'description' => $this->description,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee === null
                ? null
                : ['id' => $this->assignee->public_id, 'name' => $this->assignee->name]),
            'resolution_code' => $this->resolution_code,
            'resolution_note' => $this->resolution_note,
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(
                fn ($message): array => [
                    'id' => $message->id,
                    'sender' => [
                        'id' => $message->sender->public_id,
                        'name' => $message->sender->name,
                    ],
                    'type' => $message->message_type,
                    'body' => $message->body,
                    'created_at' => $message->created_at,
                ],
            )),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(
                fn ($attachment): array => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'file_url' => route('api.v1.ticket-attachments.file', [
                        'attachment' => $attachment,
                    ], false),
                ],
            )),
            'created_at' => $this->created_at,
            'resolved_at' => $this->resolved_at,
        ];
    }
}
