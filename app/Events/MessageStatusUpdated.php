<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MessageStatusUpdated implements ShouldBroadcastNow
{
    /**
     * @param list<int> $messageIds
     */
    public function __construct(
        public readonly int $recipientUserId,
        public readonly array $messageIds,
        public readonly string $status,
        public readonly int $changedByUserId,
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.'.$this->recipientUserId);
    }

    public function broadcastWith(): array
    {
        return [
            'message_ids' => $this->messageIds,
            'status' => $this->status,
            'changed_by_user_id' => $this->changedByUserId,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
