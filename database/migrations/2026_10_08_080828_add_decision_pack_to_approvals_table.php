<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->dropUnique(['lpj_version_id', 'level']);
            $table->string('authority_policy_version')->nullable()->after('notes');
            $table->string('authority_reference')->nullable()->after('authority_policy_version');
            $table->json('authority_snapshot')->nullable()->after('authority_reference');
            $table->json('decision_pack')->nullable()->after('authority_snapshot');
            $table->string('decision_pack_checksum', 64)->nullable()->after('decision_pack');
            $table->string('lpj_checksum', 64)->nullable()->after('decision_pack_checksum');
            $table->index(['lpj_version_id', 'level', 'decided_at']);
        });

        DB::table('approvals')->update([
            'authority_policy_version' => 'legacy-v1',
            'authority_reference' => 'LEGACY-DIRECT',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->dropIndex(['lpj_version_id', 'level', 'decided_at']);
            $table->dropColumn([
                'authority_policy_version',
                'authority_reference',
                'authority_snapshot',
                'decision_pack',
                'decision_pack_checksum',
                'lpj_checksum',
            ]);
            $table->unique(['lpj_version_id', 'level']);
        });
    }
};
