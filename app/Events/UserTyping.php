<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class UserTyping implements ShouldBroadcastNow
{
    public function __construct(
        public readonly int $senderId,
        public readonly string $senderName,
        public readonly int $receiverId,
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.'.$this->receiverId);
    }

    public function broadcastWith(): array
    {
        return [
            'sender_id' => $this->senderId,
            'sender_name' => $this->senderName,
            'typing' => true,
            'at' => now()->toIso8601String(),
        ];
    }
}
