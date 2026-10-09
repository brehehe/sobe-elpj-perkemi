<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('activities')
            ->whereNull('plan_id')
            ->orderBy('id')
            ->each(function (object $activity): void {
                $now = now();
                $planId = (string) Str::uuid7();
                $planVersionId = (string) Str::uuid7();
                $budgetId = (string) Str::uuid7();
                $budgetVersionId = (string) Str::uuid7();
                $budgetLineId = (string) Str::uuid7();
                $suffix = Str::upper(substr(str_replace([' ', '/'], '-', $activity->code), 0, 22)).'-'.substr($activity->id, 0, 8);
                $planSnapshot = $this->json($activity->plan_snapshot);
                $budgetSnapshot = $this->json($activity->budget_snapshot);
                $objective = (string) ($planSnapshot['objective'] ?? $planSnapshot['objectives'][0] ?? $activity->description ?? 'Migrasi planning kegiatan lama.');
                $planPayload = [
                    'activity_type' => 'LAINNYA',
                    'objective' => $objective,
                    'output' => (string) ($planSnapshot['output'] ?? 'Output kegiatan lama sesuai dokumen sumber.'),
                    'outcome' => (string) ($planSnapshot['outcome'] ?? 'Outcome kegiatan lama sesuai dokumen sumber.'),
                    'target_participants' => 1,
                    'kpis' => [['name' => 'Pelaksanaan kegiatan', 'target' => 1, 'unit' => 'kegiatan']],
                    'schedule' => ['starts_at' => $activity->starts_at, 'ends_at' => $activity->ends_at],
                    'resources' => [],
                    'risks' => [],
                ];
                $total = (string) ($budgetSnapshot['ceiling'] ?? $activity->budget_ceiling ?? 0);
                $budgetLines = [[
                    'line_number' => 1,
                    'account_code' => 'LEGACY',
                    'description' => 'Pagu agregat hasil migrasi kegiatan lama',
                    'quantity' => 1,
                    'unit' => 'paket',
                    'unit_cost' => $total,
                    'subtotal' => $total,
                ]];

                DB::table('plans')->insert([
                    'id' => $planId, 'mandate_id' => $activity->mandate_id, 'unit_id' => $activity->unit_id,
                    'owner_id' => $activity->pic_id, 'code' => 'LEGACY-REN-'.$suffix, 'status' => 'APPROVED',
                    'current_version' => 1, 'submitted_at' => $now, 'approved_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('plan_versions')->insert([
                    'id' => $planVersionId, 'plan_id' => $planId, 'created_by' => $activity->pic_id, 'reviewed_by' => null,
                    'version' => 1, 'status' => 'APPROVED',
                    'activity_type' => $planPayload['activity_type'], 'objective' => $planPayload['objective'],
                    'output' => $planPayload['output'], 'outcome' => $planPayload['outcome'],
                    'target_participants' => 1,
                    'kpis' => json_encode($planPayload['kpis'], JSON_THROW_ON_ERROR),
                    'schedule' => json_encode($planPayload['schedule'], JSON_THROW_ON_ERROR),
                    'resources' => json_encode([], JSON_THROW_ON_ERROR), 'risks' => json_encode([], JSON_THROW_ON_ERROR),
                    'change_reason' => 'Backfill otomatis dari kegiatan sebelum versioning.', 'review_notes' => 'Data hasil migrasi; perlu ditinjau pada perubahan berikutnya.',
                    'checksum' => hash('sha256', json_encode($planPayload, JSON_THROW_ON_ERROR)), 'is_immutable' => true,
                    'submitted_at' => $now, 'reviewed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('budgets')->insert([
                    'id' => $budgetId, 'plan_id' => $planId, 'unit_id' => $activity->unit_id, 'owner_id' => $activity->pic_id,
                    'code' => 'LEGACY-RAB-'.$suffix, 'status' => 'APPROVED', 'current_version' => 1, 'currency' => 'IDR',
                    'submitted_at' => $now, 'approved_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('budget_versions')->insert([
                    'id' => $budgetVersionId, 'budget_id' => $budgetId, 'created_by' => $activity->pic_id, 'reviewed_by' => null,
                    'version' => 1, 'status' => 'APPROVED', 'total' => $total,
                    'change_reason' => 'Backfill otomatis dari pagu kegiatan lama.', 'review_notes' => 'Baris agregat harus dirinci pada revisi berikutnya.',
                    'checksum' => hash('sha256', json_encode($budgetLines, JSON_THROW_ON_ERROR)), 'is_immutable' => true,
                    'submitted_at' => $now, 'reviewed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('budget_lines')->insert([
                    'id' => $budgetLineId, 'budget_version_id' => $budgetVersionId, ...$budgetLines[0],
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('activities')->where('id', $activity->id)->update([
                    'plan_id' => $planId, 'budget_id' => $budgetId, 'plan_version' => 1, 'budget_version' => 1,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $planIds = DB::table('plans')->whereLike('code', 'LEGACY-REN-%')->pluck('id');
        $budgetIds = DB::table('budgets')->whereIn('plan_id', $planIds)->pluck('id');
        DB::table('activities')->whereIn('plan_id', $planIds)->update(['plan_id' => null, 'budget_id' => null]);
        DB::table('budgets')->whereIn('id', $budgetIds)->delete();
        DB::table('plans')->whereIn('id', $planIds)->delete();
    }

    /** @return array<string, mixed> */
    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
