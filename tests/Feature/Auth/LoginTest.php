<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    public function test_login_page_is_available_to_guests(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/login'));
    }

    public function test_active_verified_user_can_login_and_login_is_audited(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->forUnit($unit)->create(['password' => 'secret-password', 'is_active' => true]);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $user->id, 'event_type' => 'auth.login_succeeded']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->forUnit($unit)->create(['password' => 'secret-password', 'is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, AuditEvent::query()->where('event_type', 'auth.login_succeeded')->count());
    }
}
