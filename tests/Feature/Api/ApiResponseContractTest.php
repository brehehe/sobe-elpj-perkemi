<?php

namespace Tests\Feature\Api;

use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class ApiResponseContractTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_successful_api_response_contains_the_request_id(): void
    {
        $owner = $this->userWithRole('Owner/PIC', Unit::factory()->create());
        Sanctum::actingAs($owner);

        $this->withHeader('X-Request-ID', 'api-list-001')
            ->getJson('/api/v1/lpjs')
            ->assertOk()
            ->assertHeader('X-Request-ID', 'api-list-001')
            ->assertJsonPath('request_id', 'api-list-001')
            ->assertJsonStructure(['data', 'request_id']);
    }

    public function test_validation_error_uses_the_machine_readable_api_contract(): void
    {
        $finance = $this->userWithRole('Finance PIC', Unit::factory()->create());
        Sanctum::actingAs($finance);

        $this->withHeader('X-Request-ID', 'api-validation-001')
            ->postJson('/api/v1/integrations/finance/transactions')
            ->assertUnprocessable()
            ->assertHeader('X-Request-ID', 'api-validation-001')
            ->assertJsonPath('message', 'Validasi gagal.')
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('request_id', 'api-validation-001')
            ->assertJsonValidationErrors([
                'provider',
                'idempotency_key',
                'activity_id',
                'external_id',
                'description',
                'transaction_date',
                'amount',
            ]);
    }

    public function test_unauthenticated_error_uses_the_machine_readable_api_contract(): void
    {
        $response = $this->getJson('/api/v1/lpjs');
        $requestId = (string) $response->headers->get('X-Request-ID');

        $response->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED')
            ->assertJsonPath('request_id', $requestId)
            ->assertJsonStructure(['message', 'code', 'errors', 'request_id']);
    }

    public function test_internal_api_error_is_sanitized_and_traceable(): void
    {
        Route::get('/api/v1/request-contract/server-error', function (): never {
            throw new RuntimeException('Sensitive provider credential.');
        });

        $response = $this->getJson('/api/v1/request-contract/server-error');
        $requestId = (string) $response->headers->get('X-Request-ID');

        $response->assertInternalServerError()
            ->assertJsonPath('message', 'Layanan mengalami gangguan. Gunakan Request ID saat menghubungi dukungan.')
            ->assertJsonPath('code', 'INTERNAL_ERROR')
            ->assertJsonPath('request_id', $requestId)
            ->assertJsonStructure(['message', 'code', 'errors', 'request_id']);
        $this->assertStringNotContainsString('Sensitive provider credential.', $response->getContent());
    }
}
