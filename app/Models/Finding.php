<?php

namespace App\Models;

use App\Enums\FindingStatus;
use Database\Factories\FindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['lpj_version_id', 'created_by', 'owner_id', 'resolved_by', 'code', 'severity', 'status', 'title', 'criteria', 'condition', 'cause', 'risk', 'recommendation', 'detail', 'due_at', 'resolved_at'])]
class Finding extends Model
{
    /** @use HasFactory<FindingFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['status' => FindingStatus::class, 'due_at' => 'date', 'resolved_at' => 'datetime'];
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $user = auth()->user();

        return $query->where($field ?? $this->getRouteKeyName(), $value)
            ->when($user && ! $user->isGlobalAdministrator(), fn ($builder) => $builder->whereHas('lpjVersion.lpj.activity', fn ($activity) => $activity->where('unit_id', $user->unit_id)));
    }

    public function lpjVersion(): BelongsTo
    {
        return $this->belongsTo(LpjVersion::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function correctiveActions(): HasMany
    {
        return $this->hasMany(CorrectiveAction::class);
    }
}
