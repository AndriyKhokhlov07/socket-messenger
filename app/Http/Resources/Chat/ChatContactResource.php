<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ChatContactResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $name = (string) data_get($this->resource, 'name', '');

        return [
            'id' => (int) data_get($this->resource, 'id'),
            'name' => $name,
            'avatar' => $this->buildInitials($name),
            'avatar_initials' => (string) data_get($this->resource, 'avatar_initials', $this->buildInitials($name)),
            'avatar_url' => data_get($this->resource, 'avatar_url'),
            'email' => (string) data_get($this->resource, 'email', ''),
            'online' => (bool) data_get($this->resource, 'online', false),
            'last_seen_at' => data_get($this->resource, 'last_seen_at'),
            'unread_count' => (int) data_get($this->resource, 'unread_count', 0),
            'last_message' => data_get($this->resource, 'last_message'),
            'last_message_attachment_name' => data_get($this->resource, 'last_message_attachment_name'),
            'last_message_attachment_type' => data_get($this->resource, 'last_message_attachment_type'),
            'last_message_status' => data_get($this->resource, 'last_message_status'),
            'last_message_is_mine' => (bool) data_get($this->resource, 'last_message_is_mine', false),
            'last_message_at' => data_get($this->resource, 'last_message_at'),
        ];
    }

    private function buildInitials(string $name): string
    {
        /** @var Collection<int, string> $words */
        $words = collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter(fn (string $word): bool => $word !== '')
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)));

        return $words->implode('') ?: 'U';
    }
}
