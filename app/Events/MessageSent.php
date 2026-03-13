<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Storage;

class MessageSent implements ShouldBroadcastNow
{
    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message->loadMissing(
            'sender:id,name',
            'receiver:id,name',
            'replyTo:id,sender_id,body,attachment_name,attachment_type'
        );
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.'.$this->message->receiver_id);
    }

    public function broadcastWith(): array
    {
        $hasAttachment = $this->message->attachment_path !== null && $this->message->attachment_path !== '';

        return [
            'message' => [
                'id' => $this->message->id,
                'sender_id' => $this->message->sender_id,
                'receiver_id' => $this->message->receiver_id,
                'sender_name' => $this->message->sender?->name,
                'body' => $this->message->body,
                'reply_to' => $this->message->replyTo ? [
                    'id' => $this->message->replyTo->id,
                    'sender_id' => $this->message->replyTo->sender_id,
                    'body' => $this->message->replyTo->body,
                    'attachment_name' => $this->message->replyTo->attachment_name,
                    'attachment_type' => $this->message->replyTo->attachment_type,
                ] : null,
                'has_attachment' => $hasAttachment,
                'attachment' => $hasAttachment ? [
                    'path' => $this->message->attachment_path,
                    'url' => Storage::disk('public')->url($this->message->attachment_path),
                    'name' => (string) ($this->message->attachment_name ?: 'attachment'),
                    'mime' => (string) ($this->message->attachment_mime ?: 'application/octet-stream'),
                    'size' => (int) ($this->message->attachment_size ?? 0),
                    'type' => (string) ($this->message->attachment_type ?: 'file'),
                ] : null,
                'status' => $this->message->status,
                'created_at' => $this->message->created_at?->toISOString(),
                'delivered_at' => $this->message->delivered_at?->toISOString(),
                'read_at' => $this->message->read_at?->toISOString(),
            ],
        ];
    }
}
