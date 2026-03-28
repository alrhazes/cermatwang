<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page_when_visiting_chat(): void
    {
        $this->get('/chat')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_chat(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/chat')->assertOk();
    }

    public function test_legacy_dashboard_path_redirects_to_chat(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertRedirect('/chat');
    }
}
