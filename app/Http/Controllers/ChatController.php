<?php

namespace App\Http\Controllers;

use App\Http\Resources\Chat\ChatContactResource;
use App\Models\Message;
use App\Services\Chat\ConversationService;
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
}
