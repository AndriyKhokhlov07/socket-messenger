<?php

namespace App\Services\Chat;

use App\Events\MessageSent;
use App\Events\MessageStatusUpdated;
use App\Events\UserTyping;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

class MessageService
{
    public function send(User $sender, User $receiver, ?string $body = null, ?UploadedFile $attachment = null): Message
    {
        $normalizedBody = trim((string) $body);
        $attachmentMeta = $this->storeAttachment($attachment);

        $message = Message::query()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'body' => $normalizedBody,
            'attachment_path' => $attachmentMeta['path'] ?? null,
            'attachment_name' => $attachmentMeta['name'] ?? null,
            'attachment_mime' => $attachmentMeta['mime'] ?? null,
            'attachment_size' => $attachmentMeta['size'] ?? null,
            'attachment_type' => $attachmentMeta['type'] ?? null,
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

    /**
     * @return array{path: string, name: string, mime: string, size: int, type: string}|null
     */
    private function storeAttachment(?UploadedFile $attachment): ?array
    {
        if ($attachment === null) {
            return null;
        }

        $mime = (string) ($attachment->getClientMimeType() ?: $attachment->getMimeType() ?: 'application/octet-stream');
        $path = $attachment->store('chat-attachments/'.now()->format('Y/m'), 'public');

        return [
            'path' => $path,
            'name' => $attachment->getClientOriginalName(),
            'mime' => $mime,
            'size' => (int) ($attachment->getSize() ?? 0),
            'type' => $this->resolveAttachmentType($mime),
        ];
    }

    private function resolveAttachmentType(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }

        return 'file';
    }
}
