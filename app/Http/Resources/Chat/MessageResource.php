<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authUserId = $request->user()?->id;

        return [
            'id' => (int) $this->id,
            'sender_id' => (int) $this->sender_id,
            'receiver_id' => (int) $this->receiver_id,
            'body' => (string) $this->body,
            'status' => (string) $this->status,
            'is_mine' => $authUserId !== null && (int) $this->sender_id === $authUserId,
            'created_at' => $this->created_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
        ];
    }
}
