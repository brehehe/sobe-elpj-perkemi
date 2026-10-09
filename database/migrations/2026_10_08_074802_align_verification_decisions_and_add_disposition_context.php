<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('verifications')->where('status', 'PASSED')->update(['status' => 'VERIFIED']);
        DB::table('verifications')->where('status', 'CLARIFICATION')->update(['status' => 'CLARIFICATION_REQUIRED']);
        DB::table('verifications')->where('status', 'FAILED')->update(['status' => 'RETURNED']);

        $now = now();
        DB::table('lpj_versions')->orderBy('id')->get()->each(function (object $version) use ($now): void {
            $hasSystemVerification = DB::table('verifications')
                ->where('lpj_version_id', $version->id)
                ->where('stage', 'SYSTEM')
                ->exists();
            if ($hasSystemVerification) {
                return;
            }

            $snapshot = json_decode((string) $version->snapshot, true);
            $snapshot = is_array($snapshot) ? $snapshot : [];
            $evidenceIds = collect($snapshot['evidence'] ?? [])->pluck('id')->filter()->values()->all();

            DB::table('verifications')->insert([
                'id' => (string) Str::uuid(),
                'lpj_version_id' => $version->id,
                'verifier_id' => null,
                'stage' => 'SYSTEM',
                'status' => 'VERIFIED',
                'notes' => 'Pemeriksaan sistem versi lama dibentuk ulang saat kebijakan verifikasi dimigrasikan.',
                'checklist' => json_encode($snapshot['system_checks'] ?? [], JSON_THROW_ON_ERROR),
                'evidence_ids' => json_encode($evidenceIds, JSON_THROW_ON_ERROR),
                'material_exception' => null,
                'verified_at' => $version->submitted_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        Schema::table('dispositions', function (Blueprint $table) {
            $table->string('context')->nullable()->after('status')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('verifications')
            ->where('stage', 'SYSTEM')
            ->where('notes', 'Pemeriksaan sistem versi lama dibentuk ulang saat kebijakan verifikasi dimigrasikan.')
            ->delete();

        Schema::table('dispositions', function (Blueprint $table) {
            $table->dropIndex(['context']);
            $table->dropColumn('context');
        });

        DB::table('verifications')->where('status', 'VERIFIED')->update(['status' => 'PASSED']);
        DB::table('verifications')->where('status', 'CLARIFICATION_REQUIRED')->update(['status' => 'CLARIFICATION']);
        DB::table('verifications')->whereIn('status', ['RETURNED', 'FINDING'])->update(['status' => 'FAILED']);
    }
};
