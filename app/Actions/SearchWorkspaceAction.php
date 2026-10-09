<?php

namespace App\Actions;

use App\Models\Activity;
use App\Models\Evidence;
use App\Models\FinancialTransaction;
use App\Models\Finding;
use App\Models\Lpj;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SearchWorkspaceAction
{
    public const LIMIT_PER_TYPE = 5;

    /**
     * @return list<array{id: string, type: string, type_label: string, title: string, subtitle: string, meta: string, status: string, href: string}>
     */
    public function handle(User $user, string $term): array
    {
        $pattern = '%'.$term.'%';
        $results = collect();

        if ($user->can('viewAny', Activity::class)) {
            $results = $results->concat($this->activities($user, $term, $pattern));
        }

        if ($user->can('viewAny', Lpj::class)) {
            $results = $results->concat($this->lpjs($user, $pattern));
        }

        if ($user->can('viewAny', FinancialTransaction::class)) {
            $results = $results->concat($this->transactions($user, $pattern));
        }

        if ($user->can('viewAny', Evidence::class)) {
            $results = $results->concat($this->evidence($user, $pattern));
        }

        if ($user->can('viewAny', Finding::class)) {
            $results = $results->concat($this->findings($user, $term, $pattern));
        }

        if ($user->can('hierarchy.view')) {
            $results = $results->concat($this->units($term, $pattern));
        }

        return $results->values()->all();
    }

    /** @return Collection<int, array{id: string, type: string, type_label: string, title: string, subtitle: string, meta: string, status: string, href: string}> */
    private function activities(User $user, string $term, string $pattern): Collection
    {
        return Activity::query()
            ->select(['id', 'unit_id', 'code', 'title', 'description', 'location', 'status', 'updated_at'])
            ->with('unit:id,code,name')
            ->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query->where('unit_id', $user->unit_id))
            ->where(fn (Builder $query) => $query
                ->whereLike('code', $pattern)
                ->orWhereLike('title', $pattern)
                ->orWhereLike('description', $pattern)
                ->orWhereLike('location', $pattern))
            ->orderByRaw(
                'CASE WHEN LOWER(code) = LOWER(?) THEN 0 WHEN LOWER(SUBSTR(code, 1, ?)) = LOWER(?) THEN 1 ELSE 2 END',
                [$term, mb_strlen($term), $term],
            )
            ->latest('updated_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn (Activity $activity): array => [
                'id' => 'activity-'.$activity->id,
                'type' => 'activity',
                'type_label' => 'Kegiatan',
                'title' => $activity->code.' · '.$activity->title,
                'subtitle' => $activity->unit->name,
                'meta' => $activity->location ?: 'Lokasi belum diisi',
                'status' => $activity->status->value,
                'href' => route('activities.show', $activity, false),
            ]);
    }

    /** @return Collection<int, array{id: string, type: string, type_label: string, title: string, subtitle: string, meta: string, status: string, href: string}> */
    private function lpjs(User $user, string $pattern): Collection
    {
        return Lpj::query()
            ->select(['id', 'activity_id', 'status', 'current_version', 'updated_at'])
            ->with('activity.unit:id,code,name')
            ->when(
                ! $user->isGlobalAdministrator() && ! $user->can('lpjs.pb_verify'),
                fn (Builder $query) => $query->whereHas('activity', fn (Builder $activity) => $activity->where('unit_id', $user->unit_id)),
            )
            ->where(fn (Builder $query) => $query
                ->whereLike('id', $pattern)
                ->orWhereHas('activity', fn (Builder $activity) => $activity
                    ->whereLike('code', $pattern)
                    ->orWhereLike('title', $pattern)))
            ->latest('updated_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn (Lpj $lpj): array => [
                'id' => 'lpj-'.$lpj->id,
                'type' => 'lpj',
                'type_label' => 'e-LPJ',
                'title' => 'LPJ '.$lpj->activity->code,
                'subtitle' => $lpj->activity->title,
                'meta' => $lpj->activity->unit->name.' · v'.$lpj->current_version,
                'status' => $lpj->status->value,
                'href' => route('lpjs.show', $lpj, false),
            ]);
    }

    /** @return Collection<int, array{id: string, type: string, type_label: string, title: string, subtitle: string, meta: string, status: string, href: string}> */
    private function transactions(User $user, string $pattern): Collection
    {
        return FinancialTransaction::query()
            ->select(['id', 'activity_id', 'reference_number', 'external_id', 'account_code', 'description', 'source', 'status', 'transaction_date', 'updated_at'])
            ->with('activity:id,unit_id,code,title')
            ->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query
                ->whereHas('activity', fn (Builder $activity) => $activity->where('unit_id', $user->unit_id)))
            ->where(fn (Builder $query) => $query
                ->whereLike('reference_number', $pattern)
                ->orWhereLike('external_id', $pattern)
                ->orWhereLike('account_code', $pattern)
                ->orWhereLike('description', $pattern)
                ->orWhereHas('activity', fn (Builder $activity) => $activity->whereLike('code', $pattern)))
            ->latest('updated_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn (FinancialTransaction $transaction): array => [
                'id' => 'transaction-'.$transaction->id,
                'type' => 'transaction',
                'type_label' => 'Transaksi',
                'title' => ($transaction->reference_number ?: $transaction->external_id).' · '.$transaction->description,
                'subtitle' => $transaction->activity->code.' · '.$transaction->activity->title,
                'meta' => $transaction->source.' · '.$transaction->transaction_date->format('d M Y'),
                'status' => $transaction->status,
                'href' => route('activities.show', $transaction->activity_id, false).'#activity-transactions',
            ]);
    }

    /** @return Collection<int, array{id: string, type: string, type_label: string, title: string, subtitle: string, meta: string, status: string, href: string}> */
    private function evidence(User $user, string $pattern): Collection
    {
        return Evidence::query()
            ->select(['id', 'activity_id', 'type', 'title', 'original_name', 'sha256', 'scan_status', 'review_status', 'updated_at'])
            ->with('activity:id,unit_id,code,title')
            ->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query
                ->whereHas('activity', fn (Builder $activity) => $activity->where('unit_id', $user->unit_id)))
            ->where(fn (Builder $query) => $query
                ->whereLike('title', $pattern)
                ->orWhereLike('original_name', $pattern)
                ->orWhereLike('sha256', $pattern)
                ->orWhereLike('type', $pattern)
                ->orWhereHas('activity', fn (Builder $activity) => $activity->whereLike('code', $pattern)))
            ->latest('updated_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn (Evidence $evidence): array => [
                'id' => 'evidence-'.$evidence->id,
                'type' => 'evidence',
                'type_label' => 'Bukti',
                'title' => $evidence->title,
                'subtitle' => $evidence->activity->code.' · '.$evidence->original_name,
                'meta' => $evidence->type,
                'status' => $evidence->lifecycleStatus(),
                'href' => route('activities.show', $evidence->activity_id, false).'#activity-evidence',
            ]);
    }

    /** @return Collection<int, array{id: string, type: string, type_label: string, title: string, subtitle: string, meta: string, status: string, href: string}> */
    private function findings(User $user, string $term, string $pattern): Collection
    {
        return Finding::query()
            ->select(['id', 'lpj_version_id', 'code', 'title', 'detail', 'severity', 'status', 'updated_at'])
            ->with('lpjVersion.lpj.activity:id,unit_id,code,title')
            ->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query
                ->whereHas('lpjVersion.lpj.activity', fn (Builder $activity) => $activity->where('unit_id', $user->unit_id)))
            ->where(fn (Builder $query) => $query
                ->whereLike('code', $pattern)
                ->orWhereLike('title', $pattern)
                ->orWhereLike('detail', $pattern)
                ->orWhereHas('lpjVersion.lpj.activity', fn (Builder $activity) => $activity->whereLike('code', $pattern)))
            ->orderByRaw(
                'CASE WHEN LOWER(code) = LOWER(?) THEN 0 WHEN LOWER(SUBSTR(code, 1, ?)) = LOWER(?) THEN 1 ELSE 2 END',
                [$term, mb_strlen($term), $term],
            )
            ->latest('updated_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn (Finding $finding): array => [
                'id' => 'finding-'.$finding->id,
                'type' => 'finding',
                'type_label' => 'Temuan',
                'title' => $finding->code.' · '.$finding->title,
                'subtitle' => $finding->lpjVersion->lpj->activity->code.' · '.$finding->lpjVersion->lpj->activity->title,
                'meta' => $finding->severity,
                'status' => $finding->status->value,
                'href' => route('lpjs.show', $finding->lpjVersion->lpj_id, false).'#lpj-findings',
            ]);
    }

    /** @return Collection<int, array{id: string, type: string, type_label: string, title: string, subtitle: string, meta: string, status: string, href: string}> */
    private function units(string $term, string $pattern): Collection
    {
        return Unit::query()
            ->select(['id', 'code', 'name', 'type', 'is_active', 'updated_at'])
            ->where(fn (Builder $query) => $query
                ->whereLike('code', $pattern)
                ->orWhereLike('name', $pattern)
                ->orWhereLike('type', $pattern))
            ->orderByRaw(
                'CASE WHEN LOWER(code) = LOWER(?) THEN 0 WHEN LOWER(SUBSTR(code, 1, ?)) = LOWER(?) THEN 1 ELSE 2 END',
                [$term, mb_strlen($term), $term],
            )
            ->orderBy('name')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn (Unit $unit): array => [
                'id' => 'unit-'.$unit->id,
                'type' => 'unit',
                'type_label' => 'Unit',
                'title' => $unit->code.' · '.$unit->name,
                'subtitle' => $unit->type,
                'meta' => 'Hierarki organisasi',
                'status' => $unit->is_active ? 'ACTIVE' : 'INACTIVE',
                'href' => route('organization.hierarchy', absolute: false).'#unit-'.$unit->id,
            ]);
    }
}
