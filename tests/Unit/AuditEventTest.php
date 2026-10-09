<?php

namespace Tests\Unit;

use App\Models\AuditEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditEventTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_audit_event_cannot_be_updated(): void
    {
        $event = AuditEvent::factory()->create();

        $this->expectException(LogicException::class);
        $event->update(['event_type' => 'tampered']);
    }

    public function test_audit_event_cannot_be_deleted(): void
    {
        $event = AuditEvent::factory()->create();

        $this->expectException(LogicException::class);
        $event->delete();
    }
}
