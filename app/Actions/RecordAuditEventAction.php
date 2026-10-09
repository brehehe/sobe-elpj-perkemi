<?php

namespace App\Actions;

use App\Models\Activity;
use App\Models\Archive;
use App\Models\AuditEvent;
use App\Models\Budget;
use App\Models\Disposition;
use App\Models\Evidence;
use App\Models\Finding;
use App\Models\IntegrationRun;
use App\Models\Lpj;
use App\Models\LpjVersion;
use App\Models\Plan;
use App\Models\Reconciliation;
use App\Models\ReconciliationException;
use App\Models\Unit;
use App\Models\User;
use App\Models\VerifiedPack;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RecordAuditEventAction
{
    /** @param array<string, mixed> $payload */
    public function handle(
        Model $auditable,
        string $eventType,
        ?User $actor = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        array $payload = [],
        string $result = 'SUCCEEDED',
        ?string $authority = null,
        array $evidenceReferences = [],
        ?string $objectVersion = null,
    ): AuditEvent {
        $request = app()->bound('request') && request()->route() !== null ? request() : null;
        $correlationId = $request?->header('X-Correlation-ID') ?: (string) Str::uuid7();
        $requestId = $request?->header('X-Request-ID') ?: (string) Str::uuid7();

        return AuditEvent::create([
            'unit_id' => $this->resolveUnitId($auditable),
            'actor_id' => $actor?->id,
            'event_id' => (string) Str::uuid7(),
            'correlation_id' => $correlationId,
            'request_id' => Str::limit($requestId, 100, ''),
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'object_version' => $objectVersion ?? $this->resolveObjectVersion($auditable),
            'event_type' => $eventType,
            'source_channel' => $request?->is('api/*') ? 'API' : ($request ? 'WEB' : 'SYSTEM'),
            'authority' => $authority ?? $this->resolveAuthority($actor),
            'payload_version' => 1,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'result' => $result,
            'payload' => $payload,
            'evidence_references' => $this->resolveEvidenceReferences($auditable, $payload, $evidenceReferences),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    private function resolveUnitId(Model $auditable): ?string
    {
        return match (true) {
            $auditable instanceof Unit => $auditable->id,
            $auditable instanceof Activity => $auditable->unit_id,
            $auditable instanceof Evidence => $auditable->activity->unit_id,
            $auditable instanceof Archive => $auditable->lpjVersion->lpj->activity->unit_id,
            $auditable instanceof VerifiedPack => $auditable->lpjVersion->lpj->activity->unit_id,
            $auditable instanceof Lpj => $auditable->activity->unit_id,
            $auditable instanceof LpjVersion => $auditable->lpj->activity->unit_id,
            $auditable instanceof Finding => $auditable->lpjVersion->lpj->activity->unit_id,
            $auditable instanceof IntegrationRun => $auditable->unit_id,
            $auditable instanceof Plan => $auditable->unit_id,
            $auditable instanceof Budget => $auditable->unit_id,
            $auditable instanceof Reconciliation => $auditable->transaction->activity->unit_id,
            $auditable instanceof ReconciliationException => $auditable->reconciliation->transaction->activity->unit_id,
            $auditable instanceof Disposition => $auditable->unit_id,
            default => $auditable->getAttribute('unit_id'),
        };
    }

    private function resolveAuthority(?User $actor): string
    {
        if ($actor === null) {
            return 'SYSTEM';
        }

        return $actor->getRoleNames()->sort()->implode(', ') ?: 'AUTHENTICATED';
    }

    private function resolveObjectVersion(Model $auditable): ?string
    {
        foreach (['version', 'current_version'] as $attribute) {
            $version = $auditable->getAttribute($attribute);

            if ($version !== null) {
                return (string) $version;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $explicitReferences
     * @return array<int, string>
     */
    private function resolveEvidenceReferences(Model $auditable, array $payload, array $explicitReferences): array
    {
        $references = collect($explicitReferences);

        if ($auditable instanceof Evidence) {
            $references->push((string) $auditable->getKey());
        }

        $collectReferences = function (array $values) use (&$collectReferences, $references): void {
            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    $collectReferences($value);
                }

                $normalizedKey = Str::lower((string) $key);
                $isEvidenceReference = Str::contains($normalizedKey, 'evidence')
                    && (Str::endsWith($normalizedKey, '_id') || Str::endsWith($normalizedKey, '_ids') || Str::endsWith($normalizedKey, '_references'));

                if (! $isEvidenceReference) {
                    continue;
                }

                foreach ((array) $value as $reference) {
                    if (is_string($reference) || is_int($reference)) {
                        $references->push((string) $reference);
                    }
                }
            }
        };

        $collectReferences($payload);

        return $references
            ->filter()
            ->unique()
            ->take(50)
            ->values()
            ->all();
    }
}
