<?php

namespace App\Http\Controllers;

use App\Http\Resources\Chat\ChatContactResource;
use App\Services\Chat\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
    ) {
    }

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
}
