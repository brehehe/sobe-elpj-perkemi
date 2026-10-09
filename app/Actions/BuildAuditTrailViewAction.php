<?php

namespace App\Actions;

use App\Models\Activity;
use App\Models\Approval;
use App\Models\Archive;
use App\Models\AuditEvent;
use App\Models\Budget;
use App\Models\Disposition;
use App\Models\Evaluation;
use App\Models\Evidence;
use App\Models\Finding;
use App\Models\IntegrationRun;
use App\Models\Lpj;
use App\Models\Mandate;
use App\Models\Plan;
use App\Models\Reconciliation;
use App\Models\ReconciliationException;
use App\Models\Unit;
use App\Models\User;
use App\Models\Verification;
use App\Models\VerifiedPack;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

class BuildAuditTrailViewAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(User $user, array $filters): array
    {
        [$canViewAllUnits, $selectedUnit] = $this->resolveScope($user, $filters);
        $scopeQuery = $this->scopeQuery($user, $selectedUnit);
        $filteredQuery = $this->applyFilters(clone $scopeQuery, $filters);
        $eventTypes = (clone $scopeQuery)
            ->select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type');
        $actorIds = (clone $scopeQuery)
            ->whereNotNull('actor_id')
            ->select('actor_id')
            ->distinct()
            ->pluck('actor_id');
        $events = $this->applySort(clone $filteredQuery, $filters)
            ->with(['actor:id,name', 'unit:id,code,name,type'])
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AuditEvent $event): array => $this->formatEvent($event));

        return [
            'summary' => [
                'total' => (clone $filteredQuery)->count(),
                'actors' => (clone $filteredQuery)->whereNotNull('actor_id')->distinct()->count('actor_id'),
                'state_changes' => (clone $filteredQuery)->where(fn (Builder $query) => $query->whereNotNull('from_status')->orWhereNotNull('to_status'))->count(),
                'failures' => (clone $filteredQuery)->where('result', 'FAILED')->count(),
            ],
            'scope' => [
                'can_view_all_units' => $canViewAllUnits,
                'selected_unit' => $selectedUnit?->only(['id', 'code', 'name', 'type']),
                'label' => $selectedUnit?->name ?? 'Seluruh unit',
            ],
            'events' => $events,
            'filters' => $this->normalizedFilters($filters, $selectedUnit),
            'options' => [
                'actors' => User::query()
                    ->whereIn('id', $actorIds)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->all(),
                'domains' => $eventTypes
                    ->map(fn (string $eventType): string => Str::before($eventType, '.'))
                    ->unique()
                    ->values()
                    ->map(fn (string $domain): array => ['value' => $domain, 'label' => Str::headline($domain)])
                    ->all(),
                'events' => $eventTypes
                    ->map(fn (string $eventType): array => ['value' => $eventType, 'label' => $this->eventLabel($eventType)])
                    ->all(),
                'channels' => (clone $scopeQuery)
                    ->select('source_channel')
                    ->distinct()
                    ->orderBy('source_channel')
                    ->pluck('source_channel')
                    ->all(),
                'units' => $canViewAllUnits
                    ? Unit::query()->orderBy('name')->get(['id', 'code', 'name', 'type'])->all()
                    : [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function exportRows(User $user, array $filters, string $excludedEventId): LazyCollection
    {
        [, $selectedUnit] = $this->resolveScope($user, $filters);

        return $this->applySort(
            $this->applyFilters($this->scopeQuery($user, $selectedUnit), $filters),
            $filters,
        )
            ->whereKeyNot($excludedEventId)
            ->with(['actor:id,name', 'unit:id,code,name,type'])
            ->lazy(500)
            ->map(fn (AuditEvent $event): array => $this->formatEvent($event));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{bool, Unit|null}
     */
    private function resolveScope(User $user, array $filters): array
    {
        $canViewAllUnits = $user->isGlobalAdministrator();
        $requestedUnitId = $filters['unit'] ?? null;

        if (! $canViewAllUnits) {
            abort_if($requestedUnitId !== null && $requestedUnitId !== $user->unit_id, 404);

            return [false, Unit::query()->findOrFail($user->unit_id)];
        }

        return [true, $requestedUnitId ? Unit::query()->findOrFail($requestedUnitId) : null];
    }

    private function scopeQuery(User $user, ?Unit $selectedUnit): Builder
    {
        return AuditEvent::query()
            ->when(
                $selectedUnit !== null,
                fn (Builder $query) => $query->where('unit_id', $selectedUnit->id),
            )
            ->when(
                ! $user->isGlobalAdministrator() && $selectedUnit === null,
                fn (Builder $query) => $query->where('unit_id', $user->unit_id),
            );
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, fn (Builder $events, string $search) => $events
                ->where(fn (Builder $matching) => $matching
                    ->whereLike('event_type', '%'.$search.'%')
                    ->orWhereLike('event_id', '%'.$search.'%')
                    ->orWhereLike('correlation_id', '%'.$search.'%')
                    ->orWhereLike('request_id', '%'.$search.'%')
                    ->orWhereLike('auditable_id', '%'.$search.'%')
                    ->orWhereLike('authority', '%'.$search.'%')
                    ->orWhereHas('actor', fn (Builder $actors) => $actors->whereLike('name', '%'.$search.'%'))
                    ->orWhereHas('unit', fn (Builder $units) => $units
                        ->whereLike('name', '%'.$search.'%')
                        ->orWhereLike('code', '%'.$search.'%'))))
            ->when($filters['object'] ?? null, fn (Builder $events, string $object) => $events->whereLike('auditable_id', '%'.$object.'%'))
            ->when($filters['actor'] ?? null, fn (Builder $events, string $actor) => $events->where('actor_id', $actor))
            ->when($filters['domain'] ?? null, fn (Builder $events, string $domain) => $events->whereLike('event_type', $domain.'.%'))
            ->when($filters['event'] ?? null, fn (Builder $events, string $event) => $events->where('event_type', $event))
            ->when($filters['result'] ?? null, fn (Builder $events, string $result) => $events->where('result', $result))
            ->when($filters['channel'] ?? null, fn (Builder $events, string $channel) => $events->where('source_channel', $channel))
            ->when($filters['date_from'] ?? null, fn (Builder $events, string $date) => $events->where('created_at', '>=', CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn (Builder $events, string $date) => $events->where('created_at', '<=', CarbonImmutable::createFromFormat('Y-m-d', $date)->endOfDay()));
    }

    /** @param array<string, mixed> $filters */
    private function applySort(Builder $query, array $filters): Builder
    {
        return ($filters['sort'] ?? 'latest') === 'oldest'
            ? $query->oldest('created_at')->orderBy('id')
            : $query->latest('created_at')->orderByDesc('id');
    }

    /** @return array<string, mixed> */
    private function formatEvent(AuditEvent $event): array
    {
        $payload = $this->sanitizePayload($event->payload ?? []);
        $reason = collect(['reason', 'notes', 'review_notes', 'instructions'])
            ->map(fn (string $key): mixed => data_get($payload, $key))
            ->first(fn (mixed $value): bool => is_string($value) && filled($value));

        return [
            'id' => $event->id,
            'event_id' => $event->event_id,
            'correlation_id' => $event->correlation_id,
            'request_id' => $event->request_id,
            'event_type' => $event->event_type,
            'event_label' => $this->eventLabel($event->event_type),
            'domain' => Str::before($event->event_type, '.'),
            'source_channel' => $event->source_channel,
            'result' => $event->result,
            'authority' => $event->authority,
            'payload_version' => $event->payload_version,
            'evidence_references' => $event->evidence_references ?? [],
            'created_at' => $event->created_at?->toIso8601String(),
            'actor' => $event->actor?->only(['id', 'name']) ?? ['id' => null, 'name' => 'Sistem'],
            'unit' => $event->unit?->only(['id', 'code', 'name', 'type']) ?? ['id' => null, 'code' => null, 'name' => 'Lintas sistem', 'type' => null],
            'object' => $this->objectReference($event),
            'change' => [
                'before' => $event->from_status,
                'after' => $event->to_status,
            ],
            'reason' => $reason,
            'location' => [
                'ip_address' => $this->maskIpAddress($event->ip_address),
                'user_agent' => $event->user_agent ? Str::limit($event->user_agent, 140) : null,
            ],
            'payload' => $payload,
        ];
    }

    /** @return array{label: string, type: string|null, id: string|null, version: string|null, url: string|null} */
    private function objectReference(AuditEvent $event): array
    {
        $type = $event->auditable_type;
        $id = $event->auditable_id;

        return [
            'label' => match ($type) {
                Activity::class => 'Kegiatan',
                Lpj::class => 'LPJ',
                Plan::class => 'Planning',
                Budget::class => 'RAB',
                Mandate::class => 'Mandat',
                Evidence::class => 'Evidence',
                Evaluation::class => 'Evaluasi',
                Verification::class => 'Verifikasi',
                Finding::class => 'Finding',
                Disposition::class => 'Disposisi',
                Approval::class => 'Persetujuan',
                Archive::class => 'Arsip',
                VerifiedPack::class => 'Verified Pack',
                IntegrationRun::class => 'Integration Run',
                Reconciliation::class => 'Rekonsiliasi',
                ReconciliationException::class => 'Exception Rekonsiliasi',
                User::class => 'Pengguna',
                default => $type ? Str::headline(class_basename($type)) : 'Sistem',
            },
            'type' => $type,
            'id' => $id,
            'version' => $event->object_version,
            'url' => match ($type) {
                Activity::class => $id ? '/activities/'.$id : null,
                Lpj::class => $id ? '/lpjs/'.$id : null,
                Plan::class => '/planning',
                Budget::class => '/budgets',
                Mandate::class => '/mandates',
                Evidence::class => '/workspace/evidence',
                Evaluation::class => '/workspace/evaluations',
                Verification::class => '/workspace/verifications',
                Finding::class => '/workspace/findings',
                Disposition::class => '/action-center',
                Approval::class => '/workspace/approvals',
                Archive::class, VerifiedPack::class => '/workspace/archives',
                IntegrationRun::class => '/integrations',
                Reconciliation::class, ReconciliationException::class => '/finance',
                default => null,
            },
        ];
    }

    /** @param array<string, mixed> $payload */
    private function sanitizePayload(array $payload, int $depth = 0): array
    {
        $sanitized = [];

        foreach (array_slice($payload, 0, 30, true) as $key => $value) {
            $keyName = (string) $key;
            $normalizedKey = Str::lower($keyName);

            if (Str::contains($normalizedKey, ['password', 'secret', 'api_key', 'token', 'authorization', 'cookie', 'session', 'private_key', 'recovery_code', 'two_factor'])) {
                $sanitized[$keyName] = '[DISAMARKAN]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$keyName] = $depth >= 3
                    ? '[DIPERSINGKAT]'
                    : $this->sanitizePayload($value, $depth + 1);

                continue;
            }

            $sanitized[$keyName] = is_string($value) ? Str::limit($value, 500) : $value;
        }

        if (count($payload) > 30) {
            $sanitized['_lainnya'] = count($payload) - 30;
        }

        return $sanitized;
    }

    private function maskIpAddress(?string $ipAddress): ?string
    {
        if ($ipAddress === null) {
            return null;
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+\z/', '.*', $ipAddress);
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return implode(':', array_slice(explode(':', $ipAddress), 0, 4)).'::*';
        }

        return '[TIDAK VALID]';
    }

    private function eventLabel(string $eventType): string
    {
        return Str::of($eventType)->after('.')->replace(['_', '.'], ' ')->headline()->toString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|null>
     */
    private function normalizedFilters(array $filters, ?Unit $selectedUnit): array
    {
        return [
            'q' => $filters['q'] ?? '',
            'object' => $filters['object'] ?? '',
            'unit' => $selectedUnit?->id,
            'actor' => $filters['actor'] ?? '',
            'domain' => $filters['domain'] ?? '',
            'event' => $filters['event'] ?? '',
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? '',
            'result' => $filters['result'] ?? '',
            'channel' => $filters['channel'] ?? '',
            'sort' => $filters['sort'] ?? 'latest',
        ];
    }
}
