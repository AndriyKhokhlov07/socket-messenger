<?php

namespace App\Http\Controllers;

use App\Http\Resources\Chat\ChatContactResource;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserReport;
use App\Services\Chat\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
    ) {}

    public function index(Request $request): View
    {
        return view('chat', [
            'authUser' => $request->user(),
        ]);
    }

    public function contacts(Request $request): AnonymousResourceCollection
    {
        return ChatContactResource::collection(
            $this->conversationService->contactsForUser($request->user())
        );
    }

    public function directory(Request $request): View
    {
        return view('contacts.index', [
            'contacts' => $this->conversationService->contactsForUser($request->user()),
        ]);
    }

    public function media(Request $request): View
    {
        $authUser = $request->user();
        $authUserId = $authUser->id;

        $attachments = Message::query()
            ->whereNotNull('attachment_path')
            ->where(function ($query) use ($authUserId) {
                $query->where('sender_id', $authUserId)
                    ->orWhere('receiver_id', $authUserId);
            })
            ->with(['sender:id,name', 'receiver:id,name'])
            ->latest('id')
            ->limit(120)
            ->get()
            ->map(function (Message $message) use ($authUserId): array {
                return [
                    'id' => $message->id,
                    'name' => (string) ($message->attachment_name ?: 'attachment'),
                    'mime' => (string) ($message->attachment_mime ?: 'application/octet-stream'),
                    'size' => (int) ($message->attachment_size ?? 0),
                    'type' => (string) ($message->attachment_type ?: 'file'),
                    'url' => Storage::disk('public')->url((string) $message->attachment_path),
                    'created_at' => $message->created_at?->toIso8601String(),
                    'is_mine' => $message->sender_id === $authUserId,
                    'peer_name' => $message->sender_id === $authUserId
                        ? (string) ($message->receiver?->name ?? 'Unknown user')
                        : (string) ($message->sender?->name ?? 'Unknown user'),
                ];
            });

        return view('media.index', [
            'attachments' => $attachments,
        ]);
    }

    public function contactProfile(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();

        if ($authUser->is($user)) {
            return response()->json([
                'message' => 'Contact profile for yourself is not available.',
            ], 422);
        }

        $attachmentsQuery = Message::query()
            ->betweenUsers($authUser->id, $user->id)
            ->whereNotNull('attachment_path');

        $counts = (clone $attachmentsQuery)
            ->selectRaw('attachment_type, COUNT(*) as count')
            ->groupBy('attachment_type')
            ->pluck('count', 'attachment_type');

        $recentAttachments = (clone $attachmentsQuery)
            ->latest('id')
            ->limit(12)
            ->get([
                'id',
                'attachment_path',
                'attachment_name',
                'attachment_type',
                'attachment_size',
                'created_at',
            ])
            ->map(function (Message $message): array {
                return [
                    'id' => $message->id,
                    'name' => (string) ($message->attachment_name ?: 'attachment'),
                    'type' => (string) ($message->attachment_type ?: 'file'),
                    'size' => (int) ($message->attachment_size ?? 0),
                    'url' => Storage::disk('public')->url((string) $message->attachment_path),
                    'created_at' => $message->created_at?->toIso8601String(),
                ];
            });

        $isBlockedByMe = UserBlock::query()
            ->where('blocker_id', $authUser->id)
            ->where('blocked_user_id', $user->id)
            ->exists();

        $hasBlockedMe = UserBlock::query()
            ->where('blocker_id', $user->id)
            ->where('blocked_user_id', $authUser->id)
            ->exists();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'nickname' => $user->nickname,
                'phone' => $user->phone,
                'email' => $user->email,
                'bio' => $user->bio,
                'avatar_url' => $user->avatar_url,
                'avatar_initials' => $user->initials,
                'is_blocked_by_me' => $isBlockedByMe,
                'has_blocked_me' => $hasBlockedMe,
                'shared_stats' => [
                    'images' => (int) ($counts['image'] ?? 0),
                    'videos' => (int) ($counts['video'] ?? 0),
                    'audio' => (int) ($counts['audio'] ?? 0),
                    'files' => (int) ($counts['file'] ?? 0),
                ],
                'recent_attachments' => $recentAttachments,
            ],
        ]);
    }

    public function contactActions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:block,unblock,report,delete_conversation'],
            'contact_ids' => ['required', 'array', 'min:1', 'max:100'],
            'contact_ids.*' => ['integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $authUserId = $request->user()->id;
        $contactIds = collect($validated['contact_ids'])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id !== $authUserId)
            ->unique()
            ->values();

        if ($contactIds->isEmpty()) {
            return response()->json([
                'message' => 'No valid contacts selected.',
            ], 422);
        }

        $action = (string) $validated['action'];
        $reason = (string) ($validated['reason'] ?? '');
        $affected = 0;

        if ($action === 'block') {
            $contactIds->each(function (int $contactId) use ($authUserId, $reason, &$affected): void {
                UserBlock::query()->updateOrCreate(
                    [
                        'blocker_id' => $authUserId,
                        'blocked_user_id' => $contactId,
                    ],
                    [
                        'reason' => $reason !== '' ? $reason : null,
                    ]
                );

                $affected++;
            });
        }

        if ($action === 'unblock') {
            $affected = UserBlock::query()
                ->where('blocker_id', $authUserId)
                ->whereIn('blocked_user_id', $contactIds)
                ->delete();
        }

        if ($action === 'report') {
            $contactIds->each(function (int $contactId) use ($authUserId, $reason, &$affected): void {
                UserReport::query()->create([
                    'reporter_id' => $authUserId,
                    'reported_user_id' => $contactId,
                    'reason' => $reason !== '' ? $reason : null,
                ]);

                $affected++;
            });
        }

        if ($action === 'delete_conversation') {
            $contactIds->each(function (int $contactId) use ($authUserId, &$affected): void {
                $messages = Message::query()
                    ->betweenUsers($authUserId, $contactId)
                    ->get(['id', 'attachment_path']);

                if ($messages->isEmpty()) {
                    return;
                }

                $attachmentPaths = $messages
                    ->pluck('attachment_path')
                    ->filter(fn (?string $path): bool => $path !== null && $path !== '')
                    ->values()
                    ->all();

                Message::query()
                    ->whereIn('id', $messages->pluck('id')->all())
                    ->delete();

                if ($attachmentPaths !== []) {
                    Storage::disk('public')->delete($attachmentPaths);
                }

                $affected += $messages->count();
            });
        }

        return response()->json([
            'ok' => true,
            'action' => $action,
            'affected' => $affected,
            'contacts' => $contactIds,
        ]);
    }
}
