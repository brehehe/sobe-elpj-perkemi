<?php

namespace App\Http\Controllers;

use App\Models\AiReview;
use App\Models\Approval;
use App\Models\Archive;
use App\Models\AuditEvent;
use App\Models\Evaluation;
use App\Models\Evidence;
use App\Models\FinancialTransaction;
use App\Models\Finding;
use App\Models\Mandate;
use App\Models\Participant;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    /** @var array<string, array{title: string, description: string, permission: string}> */
    private const MODULES = [
        'mandates' => ['title' => 'Mandat', 'description' => 'Sumber kewenangan untuk setiap kegiatan.', 'permission' => 'mandates.view'],
        'participants' => ['title' => 'Peserta', 'description' => 'Rekap peserta dan status kehadiran per Activity-ID.', 'permission' => 'activities.view'],
        'finance' => ['title' => 'Keuangan', 'description' => 'Transaksi sumber yang direkonsiliasi tanpa ledger bayangan.', 'permission' => 'activities.view'],
        'evidence' => ['title' => 'Bukti', 'description' => 'Berkas privat, checksum, dan keterkaitan kegiatan.', 'permission' => 'evidence.view'],
        'evaluations' => ['title' => 'Evaluasi', 'description' => 'Capaian, metrik, dan rekomendasi kegiatan.', 'permission' => 'activities.view'],
        'verifications' => ['title' => 'Verifikasi', 'description' => 'Pemeriksaan administrasi, keuangan, dan substantif.', 'permission' => 'lpjs.verify'],
        'findings' => ['title' => 'Temuan & CAPA', 'description' => 'Temuan, tenggat, dan tindakan korektif.', 'permission' => 'findings.view'],
        'approvals' => ['title' => 'Persetujuan', 'description' => 'Keputusan berjenjang dengan pemisahan tugas.', 'permission' => 'lpjs.view'],
        'archives' => ['title' => 'Arsip', 'description' => 'Paket final immutable dengan checksum.', 'permission' => 'lpjs.view'],
        'intelligence' => ['title' => 'Intelligence', 'description' => 'Rekomendasi AI terkontrol dan wajib ditinjau manusia.', 'permission' => 'ai.request'],
        'audit' => ['title' => 'Jejak audit', 'description' => 'Peristiwa append-only untuk perubahan penting.', 'permission' => 'audit.view'],
    ];

    public function show(Request $request, string $module): Response
    {
        abort_unless(isset(self::MODULES[$module]), 404);
        abort_unless($request->user()->can(self::MODULES[$module]['permission']), 403);

        if ($module === 'evidence') {
            return $this->evidenceCenter($request->user());
        }

        return Inertia::render('workspace/index', [
            'module' => $module,
            ...self::MODULES[$module],
            'rows' => $this->rows($module, $request->user()),
            'canManage' => $module === 'mandates' && $request->user()->can('create', Mandate::class),
        ]);
    }

    private function evidenceCenter(User $user): Response
    {
        $query = Evidence::query()
            ->whereHas('activity', fn (Builder $activity) => $activity->when(
                ! $user->isGlobalAdministrator(),
                fn (Builder $scopedActivity) => $scopedActivity->where('unit_id', $user->unit_id),
            ));

        $evidence = (clone $query)
            ->with(['activity:id,code,title', 'uploader:id,name', 'reviewer:id,name'])
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('evidence/index', [
            'summary' => [
                'total' => (clone $query)->count(),
                'uploading' => (clone $query)->where('scan_status', 'UPLOADING')->count(),
                'scan_failed' => (clone $query)->where('scan_status', 'SCAN_FAILED')->count(),
                'verified' => (clone $query)->where('review_status', 'VERIFIED')->count(),
            ],
            'evidence' => $evidence->map(fn (Evidence $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'type' => $item->type,
                'version' => $item->version,
                'original_name' => $item->original_name,
                'size' => $item->size,
                'sha256' => $item->sha256,
                'scan_status' => $item->scan_status->value,
                'review_status' => $item->review_status->value,
                'lifecycle_status' => $item->lifecycleStatus(),
                'access_classification' => $item->access_classification,
                'detected_mime_type' => $item->detected_mime_type,
                'scan_engine' => $item->scan_engine,
                'scan_notes' => $item->scan_notes,
                'scanned_at' => $item->scanned_at?->toIso8601String(),
                'created_at' => $item->created_at->toIso8601String(),
                'activity' => $item->activity,
                'uploader' => $item->uploader,
                'reviewer' => $item->reviewer,
                'download_url' => $item->isDownloadable()
                    ? URL::temporarySignedRoute('evidence.download', now()->addMinutes(5), ['evidence' => $item])
                    : null,
            ]),
        ]);
    }

    private function rows(string $module, User $user): Collection
    {
        $unitId = $user->unit_id;
        $global = $user->isGlobalAdministrator();

        return match ($module) {
            'mandates' => Mandate::query()->when(! $global, fn (Builder $q) => $q->where('unit_id', $unitId))->latest('issued_at')->limit(100)->get()->map(fn (Mandate $row) => ['id' => $row->id, 'primary' => $row->number, 'secondary' => $row->title, 'status' => $row->status, 'meta' => $row->issued_at->format('d M Y')]),
            'participants' => Participant::query()->whereHas('activity', fn (Builder $q) => $q->when(! $global, fn (Builder $a) => $a->where('unit_id', $unitId)))->with('activity:id,code,title')->latest()->limit(100)->get()->map(fn (Participant $row) => ['id' => $row->id, 'primary' => $row->name, 'secondary' => $row->activity->code.' · '.($row->organization ?: 'Tanpa organisasi'), 'status' => $row->attendance_status, 'meta' => $row->source]),
            'finance' => FinancialTransaction::query()->whereHas('activity', fn (Builder $q) => $q->when(! $global, fn (Builder $a) => $a->where('unit_id', $unitId)))->with('activity:id,code')->latest('transaction_date')->limit(100)->get()->map(fn (FinancialTransaction $row) => ['id' => $row->id, 'primary' => $row->description, 'secondary' => $row->activity->code.' · Rp '.number_format((float) $row->amount, 0, ',', '.'), 'status' => $row->status, 'meta' => $row->source]),
            'evidence' => Evidence::query()->whereHas('activity', fn (Builder $q) => $q->when(! $global, fn (Builder $a) => $a->where('unit_id', $unitId)))->with('activity:id,code')->latest()->limit(100)->get()->map(fn (Evidence $row) => ['id' => $row->id, 'primary' => $row->title, 'secondary' => $row->activity->code.' · '.$row->original_name, 'status' => $row->type, 'meta' => number_format($row->size / 1024, 1).' KB']),
            'evaluations' => Evaluation::query()->whereHas('activity', fn (Builder $q) => $q->when(! $global, fn (Builder $a) => $a->where('unit_id', $unitId)))->with('activity:id,code,title')->latest()->limit(100)->get()->map(fn (Evaluation $row) => ['id' => $row->id, 'primary' => $row->activity->title.' · v'.$row->version, 'secondary' => $row->summary, 'status' => $row->status->value, 'meta' => ($row->score ? $row->score.'% · ' : '').$row->created_at->format('d M Y')]),
            'verifications' => Verification::query()->whereHas('lpjVersion.lpj.activity', fn (Builder $q) => $q->when(! $global, fn (Builder $a) => $a->where('unit_id', $unitId)))->with('lpjVersion.lpj.activity:id,code,title')->latest()->limit(100)->get()->map(fn (Verification $row) => ['id' => $row->id, 'primary' => $row->lpjVersion->lpj->activity->title, 'secondary' => $row->stage->value, 'status' => $row->status->value, 'meta' => $row->verified_at?->format('d M Y H:i') ?: 'Menunggu']),
            'findings' => Finding::query()->whereHas('lpjVersion.lpj.activity', fn (Builder $q) => $q->when(! $global, fn (Builder $a) => $a->where('unit_id', $unitId)))->latest()->limit(100)->get()->map(fn (Finding $row) => ['id' => $row->id, 'primary' => $row->code.' · '.$row->title, 'secondary' => $row->detail, 'status' => $row->status->value, 'meta' => $row->due_at?->format('d M Y') ?: 'Tanpa tenggat']),
            'approvals' => Approval::query()->whereHas('lpjVersion.lpj.activity', fn (Builder $q) => $q->when(! $global, fn (Builder $a) => $a->where('unit_id', $unitId)))->with('decisionMaker:id,name')->latest('decided_at')->limit(100)->get()->map(fn (Approval $row) => ['id' => $row->id, 'primary' => $row->level->value, 'secondary' => $row->decisionMaker->name, 'status' => $row->decision->value, 'meta' => $row->decided_at->format('d M Y H:i')]),
            'archives' => Archive::query()->whereHas('lpjVersion.lpj.activity', fn (Builder $q) => $q->when(! $global, fn (Builder $a) => $a->where('unit_id', $unitId)))->latest('archived_at')->limit(100)->get()->map(fn (Archive $row) => ['id' => $row->id, 'primary' => $row->archive_number, 'secondary' => 'Checksum '.$row->checksum, 'status' => 'ARCHIVED', 'meta' => $row->archived_at->format('d M Y H:i')]),
            'intelligence' => AiReview::query()->whereHas('activity', fn (Builder $q) => $q->when(! $global, fn (Builder $a) => $a->where('unit_id', $unitId)))->with('activity:id,code,title')->latest()->limit(100)->get()->map(fn (AiReview $row) => ['id' => $row->id, 'primary' => $row->activity->title, 'secondary' => $row->type.' · '.($row->model ?: 'Menunggu'), 'status' => $row->status->value, 'meta' => 'Tinjauan: '.$row->human_review_status]),
            'audit' => AuditEvent::query()->when(! $global, fn (Builder $q) => $q->where('unit_id', $unitId))->with('actor:id,name')->latest('created_at')->limit(100)->get()->map(fn (AuditEvent $row) => ['id' => $row->id, 'primary' => $row->event_type, 'secondary' => $row->actor?->name ?: 'Sistem', 'status' => $row->to_status ?: 'EVENT', 'meta' => $row->created_at->format('d M Y H:i')]),
        };
    }
}
