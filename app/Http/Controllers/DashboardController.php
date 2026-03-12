<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use App\Services\Chat\ConversationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
    ) {}

    public function index(Request $request): View
    {
        $authUser = $request->user();
        $authUserId = $authUser->id;

        $totalUsers = User::query()->whereKeyNot($authUserId)->count();
        $unreadCount = Message::query()
            ->where('receiver_id', $authUserId)
            ->whereNull('read_at')
            ->count();

        $sentCount = Message::query()
            ->where('sender_id', $authUserId)
            ->count();

        $attachmentsCount = Message::query()
            ->whereNotNull('attachment_path')
            ->where(function ($query) use ($authUserId) {
                $query->where('sender_id', $authUserId)
                    ->orWhere('receiver_id', $authUserId);
            })
            ->count();

        $topContacts = $this->conversationService->contactsForUser($authUser)->take(4);

        return view('dashboard', [
            'metrics' => [
                'total_users' => $totalUsers,
                'unread_messages' => $unreadCount,
                'sent_messages' => $sentCount,
                'attachments' => $attachmentsCount,
            ],
            'topContacts' => $topContacts,
        ]);
    }

    public function activity(Request $request): View
    {
        $authUserId = $request->user()->id;

        $messages = Message::query()
            ->where(function ($query) use ($authUserId) {
                $query->where('sender_id', $authUserId)
                    ->orWhere('receiver_id', $authUserId);
            })
            ->with(['sender:id,name,avatar_path', 'receiver:id,name,avatar_path'])
            ->latest('id')
            ->limit(80)
            ->get();

        $sentToday = Message::query()
            ->where('sender_id', $authUserId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $receivedToday = Message::query()
            ->where('receiver_id', $authUserId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return view('activity.index', [
            'messages' => $messages,
            'stats' => [
                'sent_today' => $sentToday,
                'received_today' => $receivedToday,
                'total' => $messages->count(),
            ],
            'authUserId' => $authUserId,
        ]);
    }
}
