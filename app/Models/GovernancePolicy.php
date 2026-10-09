<?php

namespace App\Models;

use App\Enums\GovernancePolicyType;
use Database\Factories\GovernancePolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'version', 'settings', 'change_summary', 'is_active', 'created_by', 'activated_at'])]
class GovernancePolicy extends Model
{
    /** @use HasFactory<GovernancePolicyFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'type' => GovernancePolicyType::class,
            'settings' => 'array',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
