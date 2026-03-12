<?php

namespace App\Services\Chat;

use App\Events\MessageSent;
use App\Events\MessageStatusUpdated;
use App\Events\UserTyping;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;

class MessageService
{
    public function send(User $sender, User $receiver, string $body): Message
    {
        $message = Message::query()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'body' => trim($body),
            'status' => Message::STATUS_SENT,
        ]);

        $message->loadMissing('sender:id,name', 'receiver:id,name');

        broadcast(new MessageSent($message));

        return $message;
    }

    /**
     * @throws AuthorizationException
     */
    public function updateStatus(User $actor, Message $message, string $requestedStatus): bool
    {
        if ($message->receiver_id !== $actor->id) {
            throw new AuthorizationException('Only receiver can update message status.');
        }

        $normalizedStatus = $this->normalizeRequestedStatus($message->status, $requestedStatus);

        if ($normalizedStatus === null) {
            return false;
        }

        $changes = [];
        $now = now();

        if ($normalizedStatus === Message::STATUS_DELIVERED) {
            if ($message->delivered_at === null) {
                $changes['delivered_at'] = $now;
            }

            $changes['status'] = Message::STATUS_DELIVERED;
        }

        if ($normalizedStatus === Message::STATUS_READ) {
            if ($message->delivered_at === null) {
                $changes['delivered_at'] = $now;
            }

            if ($message->read_at === null) {
                $changes['read_at'] = $now;
            }

            $changes['status'] = Message::STATUS_READ;
        }

        if ($changes === []) {
            return false;
        }

        $message->forceFill($changes)->save();

        broadcast(new MessageStatusUpdated(
            recipientUserId: $message->sender_id,
            messageIds: [$message->id],
            status: $message->status,
            changedByUserId: $actor->id,
        ));

        return true;
    }

    public function sendTyping(User $sender, User $receiver): void
    {
        if ($sender->id === $receiver->id) {
            return;
        }

        $throttleKey = sprintf('chat:typing:%d:%d', $sender->id, $receiver->id);

        if (! Cache::add($throttleKey, 1, 1)) {
            return;
        }

        broadcast(new UserTyping(
            senderId: $sender->id,
            senderName: $sender->name,
            receiverId: $receiver->id,
        ));
    }

    private function normalizeRequestedStatus(string $currentStatus, string $requestedStatus): ?string
    {
        if (! in_array($requestedStatus, [Message::STATUS_DELIVERED, Message::STATUS_READ], true)) {
            return null;
        }

        if ($currentStatus === Message::STATUS_READ) {
            return null;
        }

        if ($requestedStatus === Message::STATUS_DELIVERED && $currentStatus !== Message::STATUS_SENT) {
            return null;
        }

        return $requestedStatus;
    }
}
