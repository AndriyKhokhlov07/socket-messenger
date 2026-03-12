<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspacePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_workspace_pages(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/contacts')->assertRedirect('/login');
        $this->get('/media')->assertRedirect('/login');
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_open_workspace_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/contacts')->assertOk();
        $this->actingAs($user)->get('/media')->assertOk();
        $this->actingAs($user)->get('/profile')->assertOk();
    }
}
