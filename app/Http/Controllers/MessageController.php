<?php

namespace App\Http\Controllers;

use App\Events\MessageStatusUpdated;
use App\Http\Requests\Chat\LoadMessagesRequest;
use App\Http\Requests\Chat\StoreMessageRequest;
use App\Http\Requests\Chat\TypingRequest;
use App\Http\Requests\Chat\UpdateMessageStatusRequest;
use App\Http\Resources\Chat\MessageResource;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\ConversationService;
use App\Services\Chat\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messageService,
        private readonly ConversationService $conversationService,
    ) {}

    public function index(User $user, LoadMessagesRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $authUser = $request->user();

        if ($user->is($authUser)) {
            return response()->json([
                'message' => 'Conversation with yourself is not supported.',
            ], 422);
        }

        $readMessageIds = $this->conversationService->markConversationAsRead($authUser, $user);

        if ($readMessageIds->isNotEmpty()) {
            broadcast(new MessageStatusUpdated(
                recipientUserId: $user->id,
                messageIds: $readMessageIds->all(),
                status: Message::STATUS_READ,
                changedByUserId: $authUser->id,
            ));
        }

        $payload = $this->conversationService->messagesForConversation(
            user: $authUser,
            contact: $user,
            limit: (int) ($request->input('limit') ?? 30),
            beforeId: $request->filled('before_id') ? (int) $request->input('before_id') : null,
        );

        return MessageResource::collection($payload['messages'])->additional([
            'meta' => [
                'has_more' => $payload['has_more'],
                'next_before_id' => $payload['next_before_id'],
            ],
        ]);
    }

    public function store(StoreMessageRequest $request): MessageResource
    {
        $receiver = User::query()->findOrFail((int) $request->validated('receiver_id'));
        $replyToMessage = null;

        if ($request->filled('reply_to_message_id')) {
            $replyToMessage = Message::query()
                ->betweenUsers($request->user()->id, $receiver->id)
                ->findOrFail((int) $request->validated('reply_to_message_id'));
        }

        $message = $this->messageService->send(
            sender: $request->user(),
            receiver: $receiver,
            body: (string) ($request->validated('body') ?? ''),
            attachment: $request->file('attachment'),
            replyToMessage: $replyToMessage,
        );

        return new MessageResource($message);
    }

    public function storeLegacy(StoreMessageRequest $request): MessageResource
    {
        return $this->store($request);
    }

    public function updateStatus(Message $message, UpdateMessageStatusRequest $request): JsonResponse
    {
        $updated = $this->messageService->updateStatus(
            actor: $request->user(),
            message: $message,
            requestedStatus: (string) $request->validated('status'),
        );

        return response()->json([
            'updated' => $updated,
            'message_id' => $message->id,
            'status' => $message->fresh()?->status,
        ]);
    }

    public function typing(TypingRequest $request): JsonResponse
    {
        $receiver = User::query()->findOrFail((int) $request->validated('receiver_id'));

        $this->messageService->sendTyping(
            sender: $request->user(),
            receiver: $receiver,
        );

        return response()->json([
            'ok' => true,
        ]);
    }
}
