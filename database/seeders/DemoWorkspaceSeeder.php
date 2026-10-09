<?php

namespace Database\Seeders;

use App\Actions\ApproveLpjAction;
use App\Actions\ArchiveLpjAction;
use App\Actions\OpenFindingAction;
use App\Actions\ReconcileTransactionAction;
use App\Actions\RequestAiReviewAction;
use App\Actions\ResolveReconciliationExceptionAction;
use App\Actions\ReviewAiOutputAction;
use App\Actions\SubmitLpjAction;
use App\Actions\SyncFinancialTransactionAction;
use App\Actions\VerifyLpjAction;
use App\Enums\ActivityAgendaStatus;
use App\Enums\ActivityExecutionStatus;
use App\Enums\ActivityExecutionType;
use App\Enums\ActivityStatus;
use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Enums\BudgetStatus;
use App\Enums\EvaluationStatus;
use App\Enums\EvidenceScanStatus;
use App\Enums\KpiFormula;
use App\Enums\PlanStatus;
use App\Enums\ReconciliationStatus;
use App\Enums\VerificationStage;
use App\Enums\VerificationStatus;
use App\Models\Activity;
use App\Models\ActivityAgendaItem;
use App\Models\ActivityExecutionRecord;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\BudgetVersion;
use App\Models\Evaluation;
use App\Models\Evidence;
use App\Models\FinancialTransaction;
use App\Models\Lpj;
use App\Models\Mandate;
use App\Models\Participant;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\SmartTask;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DemoWorkspaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $unit = Unit::create(['code' => 'SOBE-JBR', 'name' => 'SOBE Jawa Barat', 'type' => 'PROVINSI']);
        $pb = Unit::create(['code' => 'PB-PERKEMI', 'name' => 'PB PERKEMI', 'type' => 'PUSAT']);
        $unit->update(['parent_id' => $pb->id]);
        $accounts = [
            ['role' => 'Super Admin', 'name' => 'Admin Nasional', 'email' => 'admin@perkemi.test', 'unit' => $pb],
            ['role' => 'Owner/PIC', 'name' => 'Dimas Pratama', 'email' => 'pic@perkemi.test', 'unit' => $unit],
            ['role' => 'Finance PIC', 'name' => 'Nadia Putri', 'email' => 'finance@perkemi.test', 'unit' => $unit],
            ['role' => 'Verifier Admin', 'name' => 'Rani Kusuma', 'email' => 'verifier.admin@perkemi.test', 'unit' => $unit],
            ['role' => 'Verifier Finance', 'name' => 'Budi Santoso', 'email' => 'verifier.finance@perkemi.test', 'unit' => $unit],
            ['role' => 'Verifier Substantive', 'name' => 'Maya Anggraini', 'email' => 'verifier.substantive@perkemi.test', 'unit' => $unit],
            ['role' => 'Approver', 'name' => 'Andri Setiawan', 'email' => 'approver@perkemi.test', 'unit' => $unit],
            ['role' => 'PB Verifier', 'name' => 'Sinta Maharani', 'email' => 'pb@perkemi.test', 'unit' => $pb],
            ['role' => 'Auditor', 'name' => 'Raka Auditor', 'email' => 'auditor@perkemi.test', 'unit' => $unit],
        ];
        $users = [];
        foreach ($accounts as $account) {
            $user = User::create([
                'unit_id' => $account['unit']->id,
                'name' => $account['name'],
                'email' => $account['email'],
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);
            $user->assignRole($account['role']);
            $users[$account['role']] = $user;
        }

        $mandate = Mandate::create([
            'unit_id' => $unit->id,
            'created_by' => $users['Owner/PIC']->id,
            'number' => 'ST-042/PB-PERKEMI/IX/2026',
            'title' => 'Pelaksanaan program pembinaan dan standardisasi organisasi 2026',
            'description' => 'Mandat pelaksanaan kegiatan SOBE Jawa Barat dalam program kerja PB PERKEMI.',
            'issued_at' => now()->subMonths(2),
            'expires_at' => now()->addMonths(4),
            'status' => 'ACTIVE',
        ]);

        $draft = $this->activity($unit, $mandate, $users['Owner/PIC'], 'KGT-2026-041', 'Pelatihan Administrasi Organisasi', now()->addWeeks(2), 'PENATARAN');
        $draft->update(['status' => ActivityStatus::Planned]);
        $draft->agendaItems()->update(['status' => ActivityAgendaStatus::Planned, 'completed_by' => null, 'completed_at' => null]);
        $draft->executionRecords()->delete();
        SmartTask::create(['unit_id' => $unit->id, 'activity_id' => $draft->id, 'assignee_id' => $users['Owner/PIC']->id, 'type' => 'START_ACTIVITY', 'title' => 'Mulai pelaksanaan pelatihan administrasi', 'priority' => 'HIGH', 'status' => 'OPEN', 'source' => 'WORKFLOW', 'due_at' => $draft->starts_at]);
        app(SyncFinancialTransactionAction::class)->handle([
            'provider' => 'SIMKEU',
            'idempotency_key' => 'demo-simkeu-'.$draft->id,
            'activity_id' => $draft->id,
            'external_id' => 'ledger-demo-'.$draft->id,
            'reference_number' => 'BANK-DEMO-2026',
            'account_code' => '521211',
            'description' => 'Sinkronisasi aktual demo dari e-Finance / Bank',
            'transaction_date' => now()->toDateString(),
            'amount' => 1_250_000,
            'currency' => 'IDR',
        ], $users['Finance PIC']);
        $exceptionTransaction = FinancialTransaction::factory()->create([
            'activity_id' => $draft->id,
            'recorded_by' => $users['Finance PIC']->id,
            'amount' => 23_750_000,
            'description' => 'Paket pelaksanaan dengan penyesuaian biaya lokasi',
            'reconciliation_status' => ReconciliationStatus::Pending,
        ]);
        $draftBudgetLine = $draft->budget->currentVersionRecord->lines->firstOrFail();
        $reconciliation = app(ReconcileTransactionAction::class)->handle($exceptionTransaction, $users['Finance PIC'], [
            'budget_line_id' => $draftBudgetLine->id,
            'notes' => 'Selisih biaya lokasi memerlukan keputusan pengecualian resmi.',
        ]);
        app(ResolveReconciliationExceptionAction::class)->handle($reconciliation->exceptions->firstOrFail(), $users['Finance PIC'], [
            'resolution_type' => 'APPROVED_EXCEPTION',
            'resolution' => 'Perubahan biaya lokasi terjadi setelah persetujuan RAB dan didukung korespondensi penyedia.',
        ]);

        $verification = $this->activity($unit, $mandate, $users['Owner/PIC'], 'KGT-2026-038', 'Rapat Koordinasi Wilayah', now()->subWeeks(2), 'RAPAT');
        app(SubmitLpjAction::class)->handle($verification->lpj, $users['Owner/PIC']);
        app(VerifyLpjAction::class)->handle($verification->lpj->fresh(), $users['Verifier Admin'], $this->verificationPayload($verification->lpj->fresh(), VerificationStage::Administration, 'Dokumen administrasi lengkap.'));

        $findingActivity = $this->activity($unit, $mandate, $users['Owner/PIC'], 'KGT-2026-035', 'Kejuaraan Antar Dojo', now()->subMonth(), 'KEJUARAAN');
        app(SubmitLpjAction::class)->handle($findingActivity->lpj, $users['Owner/PIC']);
        app(OpenFindingAction::class)->handle($findingActivity->lpj->fresh(), $users['Verifier Finance'], [
            'severity' => 'HIGH',
            'title' => 'Bukti konsumsi belum lengkap',
            'criteria' => 'Setiap realisasi konsumsi wajib didukung kuitansi dan daftar penerima.',
            'condition' => 'Daftar penerima belum tersedia pada bukti versi aktif.',
            'cause' => 'Dokumentasi peserta dan bukti pembayaran belum direkonsiliasi saat penutupan kegiatan.',
            'risk' => 'Nilai transaksi tidak dapat diyakini keterjadiannya secara lengkap.',
            'recommendation' => 'Unggah kuitansi asli dan daftar penerima, kemudian ajukan CAPA untuk direview.',
            'detail' => 'Diperlukan kuitansi asli dan daftar penerima.',
            'owner_id' => $users['Owner/PIC']->id,
            'due_at' => now()->addDays(7),
        ]);

        $archivedActivity = $this->activity($unit, $mandate, $users['Owner/PIC'], 'KGT-2026-029', 'Ujian Kenaikan Tingkat Regional', now()->subMonths(2), 'UJIAN');
        $this->archiveActivity($archivedActivity, $users);

        foreach ([
            ['code' => 'KGT-2026-023', 'title' => 'Ujian Kenaikan Tingkat Pengcab', 'months_ago' => 4, 'participants' => 16, 'outcome' => 86, 'actual' => 21_600_000],
            ['code' => 'KGT-2026-017', 'title' => 'Ujian Standardisasi Regional', 'months_ago' => 6, 'participants' => 22, 'outcome' => 92, 'actual' => 24_200_000],
        ] as $profile) {
            $historicalActivity = $this->activity(
                $unit,
                $mandate,
                $users['Owner/PIC'],
                $profile['code'],
                $profile['title'],
                now()->subMonths($profile['months_ago']),
                'UJIAN',
            );
            $historicalActivity->participants()->delete();
            Participant::factory()->count($profile['participants'])->create(['activity_id' => $historicalActivity->id]);
            $transactionCount = max(1, $historicalActivity->transactions()->count());
            $historicalActivity->transactions()->update(['amount' => $profile['actual'] / $transactionCount]);
            $historicalActivity->evaluations()->update([
                'score' => $profile['outcome'],
                'completion_percent' => $profile['outcome'],
                'successful_practices' => 'Pre-registration dan checkpoint evidence per sesi berjalan baik.',
                'improvement_areas' => 'Rasio penguji dan rekonsiliasi harian perlu dipastikan sejak planning.',
            ]);
            $this->archiveActivity($historicalActivity, $users);
        }

        app(RequestAiReviewAction::class)->handleSynchronously($findingActivity, $users['Owner/PIC'], 'RISK_REVIEW');
        $acceptedReview = app(RequestAiReviewAction::class)->handleSynchronously($archivedActivity, $users['Owner/PIC'], 'PRECHECK');
        app(ReviewAiOutputAction::class)->handle($acceptedReview, $users['Verifier Substantive'], [
            'decision' => 'ACCEPTED',
            'review_notes' => 'Output sesuai dengan snapshot LPJ dan tetap digunakan sebagai masukan advisory.',
        ]);
    }

    /** @return array<string, mixed> */
    private function verificationPayload(Lpj $lpj, VerificationStage $stage, string $notes): array
    {
        $version = $lpj->currentVersionRecord()->firstOrFail();
        $checklist = data_get($version->snapshot, "verification.stages.{$stage->value}.checklist", []);

        return [
            'stage' => $stage->value,
            'status' => VerificationStatus::Verified->value,
            'notes' => $notes,
            'checklist' => collect($checklist)->map(fn (string $item): array => [
                'item' => $item,
                'result' => 'PASS',
                'notes' => null,
            ])->all(),
            'evidence_ids' => [$lpj->activity->evidence()->valueOrFail('id')],
        ];
    }

    /** @param array<string, User> $users */
    private function archiveActivity(Activity $activity, array $users): void
    {
        app(SubmitLpjAction::class)->handle($activity->lpj, $users['Owner/PIC']);
        foreach ([VerificationStage::Administration->value => 'Verifier Admin', VerificationStage::Finance->value => 'Verifier Finance', VerificationStage::Substantive->value => 'Verifier Substantive'] as $stage => $role) {
            app(VerifyLpjAction::class)->handle($activity->lpj->fresh(), $users[$role], $this->verificationPayload($activity->lpj->fresh(), VerificationStage::from($stage), 'Checklist dinyatakan lengkap.'));
        }
        app(ApproveLpjAction::class)->handle($activity->lpj->fresh(), $users['Approver'], ['level' => ApprovalLevel::Approver->value, 'decision' => ApprovalDecision::Approved->value]);
        app(ApproveLpjAction::class)->handle($activity->lpj->fresh(), $users['PB Verifier'], ['level' => ApprovalLevel::Pb->value, 'decision' => ApprovalDecision::Approved->value]);
        app(ArchiveLpjAction::class)->handle($activity->lpj->fresh(), $users['Owner/PIC']);
    }

    private function activity(Unit $unit, Mandate $mandate, User $owner, string $code, string $title, \DateTimeInterface $date, string $activityType = 'PEMBINAAN'): Activity
    {
        $planCode = str_replace('KGT-', 'REN-', $code);
        $plan = Plan::create([
            'mandate_id' => $mandate->id,
            'unit_id' => $unit->id,
            'owner_id' => $owner->id,
            'code' => $planCode,
            'status' => PlanStatus::Approved,
            'current_version' => 1,
            'submitted_at' => now()->subMonths(3),
            'approved_at' => now()->subMonths(3)->addDay(),
        ]);
        $planSnapshot = [
            'activity_type' => $activityType,
            'objective' => 'Meningkatkan tata kelola dan kapasitas organisasi.',
            'output' => 'Kegiatan terlaksana dengan dokumentasi lengkap.',
            'outcome' => 'Kapasitas organisasi dan akuntabilitas meningkat.',
            'target_participants' => 18,
            'kpis' => [[
                'name' => 'Kehadiran',
                'definition' => 'Persentase peserta yang hadir dibandingkan peserta terdaftar.',
                'baseline' => 82,
                'target' => 90,
                'unit' => 'persen',
                'formula' => KpiFormula::HigherIsBetter->value,
                'weight' => 1,
                'period_start' => $date->format('Y-m-d'),
                'period_end' => (clone $date)->modify('+1 day')->format('Y-m-d'),
            ]],
            'schedule' => ['starts_at' => $date->format(DATE_ATOM), 'ends_at' => (clone $date)->modify('+1 day')->format(DATE_ATOM)],
            'resources' => ['Tim pelaksana', 'Lokasi kegiatan'],
            'risks' => [['risk' => 'Dokumen terlambat', 'mitigation' => 'Checkpoint harian']],
        ];
        PlanVersion::create([
            ...$planSnapshot,
            'plan_id' => $plan->id,
            'created_by' => $owner->id,
            'version' => 1,
            'status' => PlanStatus::Approved,
            'checksum' => hash('sha256', json_encode($planSnapshot, JSON_THROW_ON_ERROR)),
            'is_immutable' => true,
            'submitted_at' => now()->subMonths(3),
            'reviewed_at' => now()->subMonths(3)->addDay(),
        ]);
        $budget = Budget::create([
            'plan_id' => $plan->id,
            'unit_id' => $unit->id,
            'owner_id' => $owner->id,
            'code' => str_replace('KGT-', 'RAB-', $code),
            'status' => BudgetStatus::Approved,
            'current_version' => 1,
            'currency' => 'IDR',
            'submitted_at' => now()->subMonths(2),
            'approved_at' => now()->subMonths(2)->addDay(),
        ]);
        $budgetLines = [['line_number' => 1, 'account_code' => '521211', 'description' => 'Paket pelaksanaan kegiatan', 'quantity' => 1, 'unit' => 'paket', 'unit_cost' => 25_000_000, 'subtotal' => 25_000_000]];
        $budgetVersion = BudgetVersion::create([
            'budget_id' => $budget->id,
            'created_by' => $owner->id,
            'version' => 1,
            'status' => BudgetStatus::Approved,
            'total' => 25_000_000,
            'checksum' => hash('sha256', json_encode($budgetLines, JSON_THROW_ON_ERROR)),
            'is_immutable' => true,
            'submitted_at' => now()->subMonths(2),
            'reviewed_at' => now()->subMonths(2)->addDay(),
        ]);
        $budgetLine = BudgetLine::create(['budget_version_id' => $budgetVersion->id, ...$budgetLines[0]]);
        $activity = Activity::create([
            'unit_id' => $unit->id,
            'mandate_id' => $mandate->id,
            'plan_id' => $plan->id,
            'budget_id' => $budget->id,
            'pic_id' => $owner->id,
            'code' => $code,
            'title' => $title,
            'description' => 'Kegiatan program kerja dengan dokumentasi dan pertanggungjawaban terintegrasi.',
            'location' => 'Bandung, Jawa Barat',
            'starts_at' => $date,
            'ends_at' => (clone $date)->modify('+1 day'),
            'status' => ActivityStatus::Completed,
            'plan_version' => 1,
            'budget_version' => 1,
            'budget_ceiling' => 25000000,
            'plan_snapshot' => ['version' => 1, ...$planSnapshot],
            'budget_snapshot' => ['version' => 1, 'ceiling' => 25000000],
        ]);
        $lpj = Lpj::create([
            'activity_id' => $activity->id,
            'owner_id' => $owner->id,
            'status' => 'DRAFT',
            'executive_summary' => 'Kegiatan dilaksanakan sesuai mandat dengan capaian utama yang terukur dan sumber bukti yang dapat ditelusuri.',
            'implementation_narrative' => 'Agenda utama terlaksana sesuai jadwal, melibatkan panitia dan peserta yang telah tercatat pada Activity-ID.',
            'outcome_narrative' => 'Output kegiatan tercapai dan outcome awal menunjukkan peningkatan kapasitas organisasi sesuai evaluasi yang diterima.',
            'financial_narrative' => 'Realisasi keuangan bersumber dari transaksi resmi dan seluruh transaksi telah melalui rekonsiliasi final.',
            'constraints' => 'Kendala utama berada pada ketepatan dokumentasi dan telah ditangani melalui checkpoint evidence harian.',
            'follow_up_plan' => 'Tindak lanjut difokuskan pada penguatan dokumentasi digital dan rekonsiliasi real-time pada kegiatan berikutnya.',
        ]);
        ActivityAgendaItem::create([
            'activity_id' => $activity->id,
            'completed_by' => $owner->id,
            'title' => 'Pelaksanaan agenda utama',
            'description' => 'Pembukaan, sesi inti, dan penutupan kegiatan.',
            'location' => $activity->location,
            'starts_at' => $activity->starts_at,
            'ends_at' => $activity->ends_at,
            'status' => ActivityAgendaStatus::Completed,
            'notes' => 'Agenda selesai sesuai rencana.',
            'completed_at' => $activity->ends_at,
        ]);
        ActivityExecutionRecord::create([
            'activity_id' => $activity->id,
            'created_by' => $owner->id,
            'type' => ActivityExecutionType::Progress,
            'title' => 'Seluruh agenda utama selesai',
            'details' => 'Pelaksanaan kegiatan berjalan sesuai agenda yang disetujui.',
            'progress_percent' => 100,
            'status' => ActivityExecutionStatus::Recorded,
            'occurred_at' => $activity->ends_at,
        ]);
        Participant::factory()->count(18)->create(['activity_id' => $activity->id]);
        FinancialTransaction::factory()->count(3)->create([
            'activity_id' => $activity->id,
            'budget_line_id' => $budgetLine->id,
            'recorded_by' => $owner->id,
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);
        $content = 'Demo evidence for '.$code;
        $path = 'evidence/'.$activity->id.'/demo.txt';
        Storage::disk('local')->put($path, $content);
        $evidence = Evidence::create([
            'activity_id' => $activity->id,
            'uploaded_by' => $owner->id,
            'type' => 'REPORT',
            'title' => 'Laporan pelaksanaan',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'laporan.txt',
            'mime_type' => 'text/plain',
            'detected_mime_type' => 'text/plain',
            'size' => mb_strlen($content, '8bit'),
            'sha256' => hash('sha256', $content),
            'scan_status' => EvidenceScanStatus::Registered,
            'scan_engine' => 'DEMO_SEED',
            'scan_notes' => 'Bukti demonstrasi dibuat oleh seeder lokal.',
            'scanned_at' => now(),
            'linked_at' => now(),
        ]);
        $evaluation = Evaluation::create([
            'activity_id' => $activity->id,
            'created_by' => $owner->id,
            'version' => 1,
            'status' => EvaluationStatus::Accepted,
            'summary' => 'Kegiatan terlaksana sesuai rencana dan mendapat respons positif.',
            'outcomes' => 'Target peserta dan materi tercapai.',
            'completion_percent' => 96,
            'satisfaction_score' => 4.6,
            'lessons_learned' => 'Registrasi perlu dibuka lebih awal dan evidence finance dicatat real-time.',
            'successful_practices' => 'Digital check-in dan materi terintegrasi berjalan baik.',
            'improvement_areas' => 'Rekonsiliasi harian perlu diperkuat.',
            'score' => 104.44,
            'metrics' => ['attendance_rate' => 94, 'actual_participants' => 18, 'target_participants' => 18, 'kpi_count' => 1],
            'recommendations' => ['Perluas dokumentasi digital.'],
            'formula_version' => 'kpi-achievement-v1',
            'accepted_at' => now(),
        ]);
        $definition = $evaluation->kpiDefinitions()->create([
            'position' => 0,
            'name' => 'Kehadiran',
            'definition' => 'Persentase peserta yang hadir dibandingkan peserta terdaftar.',
            'unit' => 'persen',
            'baseline' => 82,
            'target' => 90,
            'period_start' => $activity->starts_at->toDateString(),
            'period_end' => $activity->ends_at->toDateString(),
            'formula' => KpiFormula::HigherIsBetter,
            'formula_version' => 'kpi-achievement-v1',
            'weight' => 1,
        ]);
        $definition->result()->create([
            'evidence_id' => $evidence->id,
            'actual' => 94,
            'achievement_percent' => 104.44,
            'notes' => 'Dihitung dari daftar hadir kegiatan.',
        ]);

        return $activity->setRelation('lpj', $lpj);
    }
}
