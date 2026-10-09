<?php

namespace Tests\Feature\Lpjs;

use App\Actions\ApproveLpjAction;
use App\Actions\ArchiveLpjAction;
use App\Actions\SubmitLpjAction;
use App\Actions\SynchronizeLpjWorkflowTasksAction;
use App\Actions\VerifyLpjAction;
use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Enums\TaskStatus;
use App\Enums\VerificationStage;
use App\Enums\VerificationStatus;
use App\Models\Lpj;
use App\Models\LpjVersion;
use App\Models\SmartTask;
use App\Models\Unit;
use App\Notifications\WorkflowTaskAssigned;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class LpjWorkflowTaskTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_submission_creates_one_idempotent_task_for_the_authorized_next_gate(): void
    {
        Storage::fake('local');
        $workflow = $this->completeWorkflow();
        $adminVerifier = $this->userWithRole('Verifier Admin', $workflow['unit']);
        Notification::fake();

        $version = app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);
        app(SynchronizeLpjWorkflowTasksAction::class)->handle($workflow['lpj']->fresh(), $workflow['owner']);

        $task = SmartTask::query()->where('metadata->lpj_id', $workflow['lpj']->id)->firstOrFail();
        $this->assertSame('VERIFY_LPJ_ADMINISTRATION', $task->type);
        $this->assertSame('lpjs.verify.administration', $task->recipient_permission);
        $this->assertSame($adminVerifier->id, $task->assignee_id);
        $this->assertSame('LPJ', $task->subject_type);
        $this->assertSame($workflow['lpj']->id, $task->subject_id);
        $this->assertSame($version->id, data_get($task->metadata, 'lpj_version_id'));
        $this->assertSame('/lpjs/'.$workflow['lpj']->id.'#lpj-verification', data_get($task->metadata, 'action_url'));
        $this->assertNotNull($task->completion_condition);
        $this->assertNotNull($task->escalation_path);
        $this->assertSame(1, SmartTask::query()->where('deduplication_key', $task->deduplication_key)->count());
        $this->assertDatabaseHas('audit_events', [
            'auditable_type' => SmartTask::class,
            'auditable_id' => $task->id,
            'event_type' => 'workflow.task_activated',
        ]);
        Notification::assertSentTo($adminVerifier, WorkflowTaskAssigned::class);
    }

    public function test_each_completed_gate_closes_its_task_and_activates_the_next_role(): void
    {
        Storage::fake('local');
        $workflow = $this->completeWorkflow();
        $adminVerifier = $this->userWithRole('Verifier Admin', $workflow['unit']);
        $financeVerifier = $this->userWithRole('Verifier Finance', $workflow['unit']);
        $substantiveVerifier = $this->userWithRole('Verifier Substantive', $workflow['unit']);
        $approver = $this->userWithRole('Approver', $workflow['unit']);
        $archiveAdministrator = $this->userWithRole('Administrator', $workflow['unit']);
        $pbVerifier = $this->userWithRole('PB Verifier', Unit::factory()->create());

        app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);
        $this->assertActiveTask($workflow['lpj'], 'VERIFY_LPJ_ADMINISTRATION', $adminVerifier->id);

        app(VerifyLpjAction::class)->handle($workflow['lpj']->fresh(), $adminVerifier, $this->verificationPayload($workflow['lpj'], VerificationStage::Administration));
        $this->assertActiveTask($workflow['lpj'], 'VERIFY_LPJ_FINANCE', $financeVerifier->id);

        app(VerifyLpjAction::class)->handle($workflow['lpj']->fresh(), $financeVerifier, $this->verificationPayload($workflow['lpj'], VerificationStage::Finance));
        $this->assertActiveTask($workflow['lpj'], 'VERIFY_LPJ_SUBSTANTIVE', $substantiveVerifier->id);

        app(VerifyLpjAction::class)->handle($workflow['lpj']->fresh(), $substantiveVerifier, $this->verificationPayload($workflow['lpj'], VerificationStage::Substantive));
        $this->assertActiveTask($workflow['lpj'], 'APPROVE_LPJ', $approver->id);

        app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $approver, [
            'level' => ApprovalLevel::Approver->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);
        $this->assertActiveTask($workflow['lpj'], 'PB_VERIFY_LPJ', $pbVerifier->id);

        app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $pbVerifier, [
            'level' => ApprovalLevel::Pb->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);
        $this->assertActiveTask($workflow['lpj'], 'ARCHIVE_LPJ', $archiveAdministrator->id);

        app(ArchiveLpjAction::class)->handle($workflow['lpj']->fresh(), $archiveAdministrator);

        $this->assertSame(0, SmartTask::query()
            ->where('metadata->lpj_id', $workflow['lpj']->id)
            ->whereIn('status', [TaskStatus::Open->value, TaskStatus::InProgress->value])
            ->count());
        $this->assertSame(6, SmartTask::query()
            ->where('metadata->lpj_id', $workflow['lpj']->id)
            ->where('status', TaskStatus::Done->value)
            ->count());
    }

    public function test_return_activates_revision_and_resubmit_creates_a_new_version_gate(): void
    {
        Storage::fake('local');
        $workflow = $this->completeWorkflow();
        $adminVerifier = $this->userWithRole('Verifier Admin', $workflow['unit']);
        $firstVersion = app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);
        $payload = $this->verificationPayload($workflow['lpj'], VerificationStage::Administration);
        $payload['status'] = VerificationStatus::Returned->value;
        $payload['notes'] = 'Perbaiki identitas kegiatan.';
        $payload['checklist'][0]['result'] = 'FAIL';
        $payload['checklist'][0]['notes'] = 'Identitas belum konsisten.';

        app(VerifyLpjAction::class)->handle($workflow['lpj']->fresh(), $adminVerifier, $payload);
        $this->assertActiveTask($workflow['lpj'], 'REVISE_LPJ', $workflow['owner']->id);

        $workflow['activity']->evaluations()->firstOrFail()->update(['summary' => 'Identitas dan narasi telah diperbaiki.']);
        $secondVersion = app(SubmitLpjAction::class)->handle($workflow['lpj']->fresh(), $workflow['owner']);

        $this->assertSame($firstVersion->version + 1, $secondVersion->version);
        $this->assertActiveTask($workflow['lpj'], 'VERIFY_LPJ_ADMINISTRATION', $adminVerifier->id);
        $this->assertSame(1, SmartTask::query()
            ->where('metadata->lpj_id', $workflow['lpj']->id)
            ->where('type', 'REVISE_LPJ')
            ->where('status', TaskStatus::Done->value)
            ->count());
        $this->assertSame(2, SmartTask::query()
            ->where('metadata->lpj_id', $workflow['lpj']->id)
            ->where('type', 'VERIFY_LPJ_ADMINISTRATION')
            ->count());
    }

    /** @return array<string, mixed> */
    private function verificationPayload(Lpj $lpj, VerificationStage $stage): array
    {
        $version = $lpj->currentVersionRecord()->firstOrFail();

        return [
            'stage' => $stage->value,
            'status' => VerificationStatus::Verified->value,
            'notes' => 'Checklist lengkap.',
            'checklist' => $this->verificationChecklist($version, $stage),
            'evidence_ids' => [$lpj->activity->evidence()->value('id')],
        ];
    }

    /** @return list<array{item: string, result: string, notes: string|null}> */
    private function verificationChecklist(LpjVersion $version, VerificationStage $stage): array
    {
        return collect(data_get($version->snapshot, "verification.stages.{$stage->value}.checklist", []))
            ->values()
            ->map(fn (string $item): array => ['item' => $item, 'result' => 'PASS', 'notes' => null])
            ->all();
    }

    private function assertActiveTask(Lpj $lpj, string $type, string $assigneeId): void
    {
        $task = SmartTask::query()
            ->where('metadata->lpj_id', $lpj->id)
            ->whereIn('status', [TaskStatus::Open->value, TaskStatus::InProgress->value])
            ->sole();

        $this->assertSame($type, $task->type);
        $this->assertSame($assigneeId, $task->assignee_id);
    }
}
