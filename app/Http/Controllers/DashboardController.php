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
}
