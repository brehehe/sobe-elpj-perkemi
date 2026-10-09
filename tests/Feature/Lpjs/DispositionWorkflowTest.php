<?php

namespace Tests\Feature\Lpjs;

use App\Actions\SubmitLpjAction;
use App\Models\Disposition;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class DispositionWorkflowTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_disposition_is_bound_to_the_active_version_and_follows_valid_transitions(): void
    {
        $workflow = $this->completeWorkflow();
        $verifier = $this->userWithRole('Verifier Admin', $workflow['unit']);
        app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);

        $this->actingAs($verifier)->post("/lpjs/{$workflow['lpj']->id}/dispositions", [
            'to_user_id' => $workflow['owner']->id,
            'subject' => 'Lengkapi klarifikasi transaksi',
            'instructions' => 'Tambahkan penjelasan dan referensi pembayaran.',
            'priority' => 'HIGH',
            'due_at' => now()->addDays(2)->toDateTimeString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $disposition = Disposition::query()->firstOrFail();
        $this->assertSame($workflow['lpj']->fresh()->currentVersionRecord->id, $disposition->lpj_version_id);
        foreach (['ACKNOWLEDGED', 'IN_PROGRESS'] as $status) {
            $this->actingAs($workflow['owner'])->patch("/dispositions/{$disposition->id}", ['status' => $status])->assertRedirect()->assertSessionHasNoErrors();
        }
        $evidenceId = $workflow['activity']->evidence()->value('id');
        $this->actingAs($workflow['owner'])->patch("/dispositions/{$disposition->id}", [
            'status' => 'EVIDENCE_SUBMITTED',
            'evidence_ids' => [$evidenceId],
            'notes' => 'Klarifikasi dan bukti pembayaran sudah dilengkapi.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($workflow['owner'])->patch("/dispositions/{$disposition->id}", [
            'status' => 'CLOSED',
            'notes' => 'Percobaan penutupan oleh penerima.',
        ])->assertSessionHasErrors('status');

        $this->actingAs($verifier)->patch("/dispositions/{$disposition->id}", [
            'status' => 'CLOSED',
            'notes' => 'Bukti telah diperiksa dan dinilai efektif.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('CLOSED', $disposition->fresh()->status->value);
        $this->assertSame([$evidenceId], $disposition->fresh()->evidence_ids);
        $this->assertSame($verifier->id, $disposition->fresh()->reviewed_by);
        $this->assertDatabaseHas('smart_tasks', ['metadata->disposition_id' => $disposition->id, 'status' => 'DONE']);

        $this->actingAs($verifier)->patch("/dispositions/{$disposition->id}", [
            'status' => 'REOPENED',
            'notes' => 'Bukti baru menunjukkan masalah belum selesai.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('REOPENED', $disposition->fresh()->status->value);
        $this->assertDatabaseHas('smart_tasks', ['metadata->disposition_id' => $disposition->id, 'status' => 'OPEN']);
    }
}
