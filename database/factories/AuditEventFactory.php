<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'actor_id' => User::factory(),
            'event_type' => 'test.event',
            'payload' => [],
        ];
    }
}
