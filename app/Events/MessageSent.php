<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MessageSent implements ShouldBroadcastNow
{
    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message->loadMissing('sender:id,name', 'receiver:id,name');
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.' . $this->message->receiver_id);
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'sender_id' => $this->message->sender_id,
                'receiver_id' => $this->message->receiver_id,
                'sender_name' => $this->message->sender?->name,
                'body' => $this->message->body,
                'status' => $this->message->status,
                'created_at' => $this->message->created_at?->toISOString(),
                'delivered_at' => $this->message->delivered_at?->toISOString(),
                'read_at' => $this->message->read_at?->toISOString(),
            ],
        ];
    }
}
