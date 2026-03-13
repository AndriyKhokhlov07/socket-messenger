<?php

namespace App\Services\Chat;

use App\Models\Message;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class ConversationService
{
    public function __construct(
        private readonly PresenceService $presenceService,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function contactsForUser(User $user): Collection
    {
        $currentUserId = $user->id;

        $latestMessageIds = Message::query()
            ->selectRaw(
                'MAX(id) as message_id, CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as contact_id',
                [$currentUserId]
            )
            ->where(function ($query) use ($currentUserId) {
                $query->where('sender_id', $currentUserId)
                    ->orWhere('receiver_id', $currentUserId);
            })
            ->groupBy('contact_id');

        $latestMessages = Message::query()
            ->joinSub($latestMessageIds, 'latest_messages', function ($join) {
                $join->on('messages.id', '=', 'latest_messages.message_id');
            })
            ->get([
                'messages.id',
                'messages.sender_id',
                'messages.receiver_id',
                'messages.body',
                'messages.attachment_name',
                'messages.attachment_type',
                'messages.status',
                'messages.created_at',
                'latest_messages.contact_id',
            ])
            ->keyBy(fn (Message $message): int => (int) $message->contact_id);

        $unreadCounts = Message::query()
            ->where('receiver_id', $currentUserId)
            ->whereNull('read_at')
            ->selectRaw('sender_id, COUNT(*) as unread_count')
            ->groupBy('sender_id')
            ->pluck('unread_count', 'sender_id');

        $blockedByMeIds = UserBlock::query()
            ->where('blocker_id', $currentUserId)
            ->pluck('blocked_user_id')
            ->all();

        $blockedMeIds = UserBlock::query()
            ->where('blocked_user_id', $currentUserId)
            ->pluck('blocker_id')
            ->all();

        return User::query()
            ->whereKeyNot($currentUserId)
            ->orderBy('name')
            ->get(['id', 'name', 'nickname', 'phone', 'bio', 'email', 'avatar_path'])
            ->map(function (User $contact) use ($latestMessages, $unreadCounts, $currentUserId, $blockedByMeIds, $blockedMeIds) {
                $latestMessage = $latestMessages->get($contact->id);
                $lastSeenAt = $this->presenceService->lastSeenAt($contact->id);

                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'nickname' => $contact->nickname,
                    'phone' => $contact->phone,
                    'bio' => $contact->bio,
                    'email' => $contact->email,
                    'avatar_url' => $contact->avatar_url,
                    'avatar_initials' => $contact->initials,
                    'is_blocked_by_me' => in_array($contact->id, $blockedByMeIds, true),
                    'has_blocked_me' => in_array($contact->id, $blockedMeIds, true),
                    'online' => $this->presenceService->isOnline($contact->id),
                    'last_seen_at' => $lastSeenAt?->toIso8601String(),
                    'unread_count' => (int) ($unreadCounts[$contact->id] ?? 0),
                    'last_message' => $latestMessage?->body,
                    'last_message_attachment_name' => $latestMessage?->attachment_name,
                    'last_message_attachment_type' => $latestMessage?->attachment_type,
                    'last_message_status' => $latestMessage?->status,
                    'last_message_is_mine' => (bool) ($latestMessage?->sender_id === $currentUserId),
                    'last_message_at' => $latestMessage?->created_at?->toIso8601String(),
                    'last_message_at_ts' => $latestMessage?->created_at?->timestamp ?? 0,
                ];
            })
            ->sortByDesc('last_message_at_ts')
            ->sortByDesc('online')
            ->sortByDesc('unread_count')
            ->values()
            ->map(fn (array $contact): array => Arr::except($contact, ['last_message_at_ts']));
    }

    /**
     * @return array{messages: Collection<int, Message>, has_more: bool, next_before_id: ?int}
     */
    public function messagesForConversation(User $user, User $contact, int $limit = 30, ?int $beforeId = null): array
    {
        $limit = max(10, min($limit, 100));

        $query = Message::query()
            ->betweenUsers($user->id, $contact->id)
            ->with('replyTo:id,sender_id,body,attachment_name,attachment_type')
            ->orderByDesc('id');

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        $batch = $query->limit($limit + 1)->get();
        $hasMore = $batch->count() > $limit;
        $messages = $batch->take($limit)->sortBy('id')->values();

        return [
            'messages' => $messages,
            'has_more' => $hasMore,
            'next_before_id' => $hasMore ? $messages->first()?->id : null,
        ];
    }

    /**
     * Mark all incoming unread messages in a conversation as read.
     *
     * @return Collection<int, int>
     */
    public function markConversationAsRead(User $user, User $contact): Collection
    {
        $messageIds = Message::query()
            ->where('sender_id', $contact->id)
            ->where('receiver_id', $user->id)
            ->whereNull('read_at')
            ->pluck('id');

        if ($messageIds->isEmpty()) {
            return collect();
        }

        $now = now();

        Message::query()
            ->whereIn('id', $messageIds)
            ->update([
                'status' => Message::STATUS_READ,
                'delivered_at' => $now,
                'read_at' => $now,
                'updated_at' => $now,
            ]);

        return $messageIds->values();
    }
}
