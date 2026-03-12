<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
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
}
