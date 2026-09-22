<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Rating */
class RatingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'score' => $this->score,
            'tags' => $this->tags,
            'comment' => $this->comment,
            'moderation_status' => $this->moderation_status->value,
            'reviewee' => $this->whenLoaded('reviewee', fn () => [
                'id' => $this->reviewee->public_id,
                'name' => $this->reviewee->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
