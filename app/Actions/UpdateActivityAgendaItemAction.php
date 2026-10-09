<?php

namespace App\Actions;

use App\Enums\ActivityAgendaStatus;
use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\ActivityAgendaItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateActivityAgendaItemAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array<string, mixed> $data */
    public function handle(Activity $activity, ActivityAgendaItem $agendaItem, User $user, array $data): ActivityAgendaItem
    {
        return DB::transaction(function () use ($activity, $agendaItem, $user, $data): ActivityAgendaItem {
            $lockedActivity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            $lockedAgendaItem = ActivityAgendaItem::query()->lockForUpdate()->findOrFail($agendaItem->id);
            if ($lockedAgendaItem->activity_id !== $lockedActivity->id) {
                abort(404);
            }
            if ($lockedActivity->status !== ActivityStatus::InProgress) {
                throw ValidationException::withMessages(['status' => 'Status agenda hanya dapat diubah ketika kegiatan sedang berjalan.']);
            }

            $toStatus = ActivityAgendaStatus::from($data['status']);
            $allowedTransitions = [
                ActivityAgendaStatus::Planned->value => [ActivityAgendaStatus::InProgress, ActivityAgendaStatus::Cancelled],
                ActivityAgendaStatus::InProgress->value => [ActivityAgendaStatus::Completed, ActivityAgendaStatus::Cancelled],
            ];
            if (! in_array($toStatus, $allowedTransitions[$lockedAgendaItem->status->value] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Perubahan status agenda tidak valid.']);
            }

            $fromStatus = $lockedAgendaItem->status->value;
            $lockedAgendaItem->update([
                'status' => $toStatus,
                'notes' => $data['notes'] ?? null,
                'completed_by' => $toStatus === ActivityAgendaStatus::Completed ? $user->id : null,
                'completed_at' => $toStatus === ActivityAgendaStatus::Completed ? now() : null,
            ]);
            $this->recordAuditEvent->handle($lockedActivity, 'activity.agenda_status_changed', $user, $fromStatus, $toStatus->value, [
                'agenda_item_id' => $lockedAgendaItem->id,
                'notes' => $data['notes'] ?? null,
            ]);

            return $lockedAgendaItem->fresh(['completedBy']);
        }, attempts: 3);
    }
}
