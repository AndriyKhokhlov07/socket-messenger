<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_chat_endpoints(): void
    {
        $user = User::factory()->create();
        $message = Message::query()->create([
            'sender_id' => $user->id,
            'receiver_id' => User::factory()->create()->id,
            'body' => 'Hello',
            'status' => Message::STATUS_SENT,
        ]);

        $this->get('/chat/contacts')->assertRedirect('/login');
        $this->get("/messages/{$user->id}")->assertRedirect('/login');
        $this->post('/messages', [
            'receiver_id' => $user->id,
            'body' => 'Hello',
        ])->assertRedirect('/login');
        $this->post('/messages/typing', [
            'receiver_id' => $user->id,
        ])->assertRedirect('/login');
        $this->patch("/messages/{$message->id}/status", [
            'status' => Message::STATUS_READ,
        ])->assertRedirect('/login');
    }

    public function test_authenticated_user_can_send_message(): void
    {
        [$sender, $receiver] = User::factory()->count(2)->create();

        $response = $this->actingAs($sender)->postJson('/messages', [
            'receiver_id' => $receiver->id,
            'body' => 'Hello from sender',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sender_id', $sender->id)
            ->assertJsonPath('data.receiver_id', $receiver->id)
            ->assertJsonPath('data.body', 'Hello from sender')
            ->assertJsonPath('data.status', Message::STATUS_SENT);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'body' => 'Hello from sender',
            'status' => Message::STATUS_SENT,
        ]);
    }

    public function test_authenticated_user_can_send_message_with_attachment_only(): void
    {
        Storage::fake('public');
        [$sender, $receiver] = User::factory()->count(2)->create();

        $response = $this->actingAs($sender)->post('/messages', [
            'receiver_id' => $receiver->id,
            'body' => '   ',
            'attachment' => UploadedFile::fake()->create('photo.png', 256, 'image/png'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sender_id', $sender->id)
            ->assertJsonPath('data.receiver_id', $receiver->id)
            ->assertJsonPath('data.has_attachment', true)
            ->assertJsonPath('data.attachment.type', 'image');

        $message = Message::query()->latest('id')->first();

        $this->assertNotNull($message);
        $this->assertSame('', $message->body);
        $this->assertNotNull($message->attachment_path);
        $this->assertSame('image', $message->attachment_type);

        Storage::disk('public')->assertExists((string) $message->attachment_path);
    }

    public function test_loading_conversation_marks_unread_messages_as_read(): void
    {
        [$sender, $receiver] = User::factory()->count(2)->create();

        $message = Message::query()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'body' => 'Unread',
            'status' => Message::STATUS_SENT,
        ]);

        $response = $this->actingAs($receiver)->getJson("/messages/{$sender->id}");

        $response->assertOk()
            ->assertJsonPath('data.0.status', Message::STATUS_READ);

        $message->refresh();

        $this->assertSame(Message::STATUS_READ, $message->status);
        $this->assertNotNull($message->read_at);
    }

    public function test_receiver_can_update_message_status(): void
    {
        [$sender, $receiver] = User::factory()->count(2)->create();

        $message = Message::query()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'body' => 'Track status',
            'status' => Message::STATUS_SENT,
        ]);

        $this->actingAs($receiver)
            ->patchJson("/messages/{$message->id}/status", [
                'status' => Message::STATUS_DELIVERED,
            ])
            ->assertOk()
            ->assertJsonPath('updated', true)
            ->assertJsonPath('status', Message::STATUS_DELIVERED);

        $this->actingAs($receiver)
            ->patchJson("/messages/{$message->id}/status", [
                'status' => Message::STATUS_READ,
            ])
            ->assertOk()
            ->assertJsonPath('updated', true)
            ->assertJsonPath('status', Message::STATUS_READ);
    }

    public function test_sender_cannot_update_message_status(): void
    {
        [$sender, $receiver] = User::factory()->count(2)->create();

        $message = Message::query()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'body' => 'Forbidden update',
            'status' => Message::STATUS_SENT,
        ]);

        $this->actingAs($sender)
            ->patchJson("/messages/{$message->id}/status", [
                'status' => Message::STATUS_READ,
            ])
            ->assertForbidden();
    }

    public function test_contacts_endpoint_returns_unread_counts(): void
    {
        [$currentUser, $contactOne, $contactTwo] = User::factory()->count(3)->create();

        Message::query()->create([
            'sender_id' => $contactOne->id,
            'receiver_id' => $currentUser->id,
            'body' => 'A',
            'status' => Message::STATUS_SENT,
        ]);

        Message::query()->create([
            'sender_id' => $contactOne->id,
            'receiver_id' => $currentUser->id,
            'body' => 'B',
            'status' => Message::STATUS_SENT,
        ]);

        Message::query()->create([
            'sender_id' => $contactTwo->id,
            'receiver_id' => $currentUser->id,
            'body' => 'C',
            'status' => Message::STATUS_SENT,
        ]);

        $response = $this->actingAs($currentUser)->getJson('/chat/contacts');

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $contactOne->id,
                'unread_count' => 2,
            ])
            ->assertJsonFragment([
                'id' => $contactTwo->id,
                'unread_count' => 1,
            ]);
    }

    public function test_authenticated_user_can_reply_to_message(): void
    {
        [$sender, $receiver] = User::factory()->count(2)->create();

        $original = Message::query()->create([
            'sender_id' => $receiver->id,
            'receiver_id' => $sender->id,
            'body' => 'Original message',
            'status' => Message::STATUS_SENT,
        ]);

        $response = $this->actingAs($sender)->postJson('/messages', [
            'receiver_id' => $receiver->id,
            'body' => 'Reply message',
            'reply_to_message_id' => $original->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.body', 'Reply message')
            ->assertJsonPath('data.reply_to.id', $original->id)
            ->assertJsonPath('data.reply_to.sender_id', $original->sender_id);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'body' => 'Reply message',
            'reply_to_message_id' => $original->id,
        ]);
    }

    public function test_blocked_users_cannot_send_messages(): void
    {
        [$sender, $receiver] = User::factory()->count(2)->create();

        UserBlock::query()->create([
            'blocker_id' => $receiver->id,
            'blocked_user_id' => $sender->id,
            'reason' => 'blocked',
        ]);

        $this->actingAs($sender)
            ->postJson('/messages', [
                'receiver_id' => $receiver->id,
                'body' => 'Can you see this?',
            ])
            ->assertForbidden();
    }

    public function test_contact_profile_endpoint_returns_shared_attachment_stats(): void
    {
        Storage::fake('public');
        [$currentUser, $contact] = User::factory()->count(2)->create();

        Message::query()->create([
            'sender_id' => $currentUser->id,
            'receiver_id' => $contact->id,
            'body' => '',
            'attachment_path' => 'chat-attachments/2026/03/photo.png',
            'attachment_name' => 'photo.png',
            'attachment_type' => 'image',
            'attachment_size' => 1280,
            'status' => Message::STATUS_SENT,
        ]);

        Message::query()->create([
            'sender_id' => $contact->id,
            'receiver_id' => $currentUser->id,
            'body' => '',
            'attachment_path' => 'chat-attachments/2026/03/voice.ogg',
            'attachment_name' => 'voice.ogg',
            'attachment_type' => 'audio',
            'attachment_size' => 640,
            'status' => Message::STATUS_SENT,
        ]);

        $this->actingAs($currentUser)
            ->getJson("/chat/contacts/{$contact->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.id', $contact->id)
            ->assertJsonPath('data.shared_stats.images', 1)
            ->assertJsonPath('data.shared_stats.audio', 1);
    }

    public function test_contact_actions_endpoint_can_block_report_unblock_and_delete_conversation(): void
    {
        [$currentUser, $contact] = User::factory()->count(2)->create();

        Message::query()->create([
            'sender_id' => $currentUser->id,
            'receiver_id' => $contact->id,
            'body' => 'Hello',
            'status' => Message::STATUS_SENT,
        ]);
        Message::query()->create([
            'sender_id' => $contact->id,
            'receiver_id' => $currentUser->id,
            'body' => 'Hi',
            'status' => Message::STATUS_SENT,
        ]);

        $this->actingAs($currentUser)
            ->postJson('/chat/contacts/actions', [
                'action' => 'block',
                'contact_ids' => [$contact->id],
                'reason' => 'spam',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('user_blocks', [
            'blocker_id' => $currentUser->id,
            'blocked_user_id' => $contact->id,
            'reason' => 'spam',
        ]);

        $this->actingAs($currentUser)
            ->postJson('/chat/contacts/actions', [
                'action' => 'report',
                'contact_ids' => [$contact->id],
                'reason' => 'abuse',
            ])
            ->assertOk();

        $this->assertDatabaseHas('user_reports', [
            'reporter_id' => $currentUser->id,
            'reported_user_id' => $contact->id,
            'reason' => 'abuse',
        ]);

        $this->actingAs($currentUser)
            ->postJson('/chat/contacts/actions', [
                'action' => 'delete_conversation',
                'contact_ids' => [$contact->id],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('messages', [
            'sender_id' => $currentUser->id,
            'receiver_id' => $contact->id,
            'body' => 'Hello',
        ]);
        $this->assertDatabaseMissing('messages', [
            'sender_id' => $contact->id,
            'receiver_id' => $currentUser->id,
            'body' => 'Hi',
        ]);

        $this->actingAs($currentUser)
            ->postJson('/chat/contacts/actions', [
                'action' => 'unblock',
                'contact_ids' => [$contact->id],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('user_blocks', [
            'blocker_id' => $currentUser->id,
            'blocked_user_id' => $contact->id,
        ]);
    }
}
