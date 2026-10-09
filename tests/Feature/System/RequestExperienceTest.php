<?php

namespace Tests\Feature\System;

use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class RequestExperienceTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_web_response_exposes_a_valid_client_request_id(): void
    {
        $response = $this->withHeader('X-Request-ID', 'browser-request-001')->get('/login');

        $response->assertOk()->assertHeader('X-Request-ID', 'browser-request-001');
    }

    public function test_invalid_client_request_id_is_replaced(): void
    {
        $response = $this->withHeader('X-Request-ID', 'invalid request id')->get('/login');

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            (string) $response->headers->get('X-Request-ID'),
        );
    }

    public function test_missing_web_route_renders_a_recoverable_error_page(): void
    {
        $response = $this->get('/missing-operational-page');
        $requestId = (string) $response->headers->get('X-Request-ID');

        $response->assertNotFound()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertInertia(fn (Assert $page) => $page
                ->component('errors/status')
                ->where('status', 404)
                ->where('authenticated', false)
                ->where('requestId', $requestId)
                ->has('supportMessage'));
    }

    public function test_forbidden_web_route_renders_a_permission_denied_state(): void
    {
        $owner = $this->userWithRole('Owner/PIC', Unit::factory()->create());
        $response = $this->actingAs($owner)->get('/administration');
        $requestId = (string) $response->headers->get('X-Request-ID');

        $response->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('errors/status')
                ->where('status', 403)
                ->where('authenticated', true)
                ->where('requestId', $requestId));
    }

    public function test_production_server_error_hides_internal_details_and_exposes_a_request_id(): void
    {
        Route::get('/request-experience/server-error', function (): never {
            throw new RuntimeException('Sensitive infrastructure detail.');
        });

        $response = $this->get('/request-experience/server-error');
        $requestId = (string) $response->headers->get('X-Request-ID');

        $response->assertInternalServerError()
            ->assertDontSee('Sensitive infrastructure detail.')
            ->assertInertia(fn (Assert $page) => $page
                ->component('errors/status')
                ->where('status', 500)
                ->where('requestId', $requestId));
    }
}
