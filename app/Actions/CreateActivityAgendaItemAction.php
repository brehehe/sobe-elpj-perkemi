<?php

namespace App\Actions;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\ActivityAgendaItem;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateActivityAgendaItemAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array<string, mixed> $data */
    public function handle(Activity $activity, User $user, array $data): ActivityAgendaItem
    {
        return DB::transaction(function () use ($activity, $user, $data): ActivityAgendaItem {
            $lockedActivity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            if (! in_array($lockedActivity->status, [ActivityStatus::Planned, ActivityStatus::InProgress], true)) {
                throw ValidationException::withMessages(['agenda' => 'Agenda hanya dapat ditambah sebelum kegiatan selesai.']);
            }

            $startsAt = Date::parse($data['starts_at']);
            $endsAt = Date::parse($data['ends_at']);
            if ($startsAt->lt($lockedActivity->starts_at) || $endsAt->gt($lockedActivity->ends_at)) {
                throw ValidationException::withMessages(['starts_at' => 'Waktu agenda harus berada di dalam jadwal kegiatan.']);
            }

            $agendaItem = $lockedActivity->agendaItems()->create($data);
            $this->recordAuditEvent->handle($lockedActivity, 'activity.agenda_created', $user, payload: [
                'agenda_item_id' => $agendaItem->id,
                'title' => $agendaItem->title,
            ]);

            return $agendaItem;
        }, attempts: 3);
    }
}
