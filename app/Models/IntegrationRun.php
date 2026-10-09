<?php

namespace App\Models;

use App\Enums\IntegrationRetryStatus;
use App\Enums\IntegrationStatus;
use Database\Factories\IntegrationRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['unit_id', 'owner_id', 'triggered_by', 'provider', 'direction', 'contract_version', 'idempotency_key', 'correlation_id', 'status', 'attempts', 'records_received', 'records_succeeded', 'records_failed', 'duration_ms', 'payload_hash', 'request_metadata', 'response_metadata', 'last_error', 'error_category', 'retry_status', 'next_retry_at', 'last_attempted_at', 'completed_at'])]
class IntegrationRun extends Model
{
    /** @use HasFactory<IntegrationRunFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => IntegrationStatus::class,
            'retry_status' => IntegrationRetryStatus::class,
            'request_metadata' => 'array',
            'response_metadata' => 'array',
            'next_retry_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
