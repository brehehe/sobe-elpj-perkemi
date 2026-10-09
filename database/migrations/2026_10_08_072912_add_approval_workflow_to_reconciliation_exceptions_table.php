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
        Schema::table('reconciliation_exceptions', function (Blueprint $table) {
            $table->foreignUuid('requested_by')->nullable()->after('resolved_by')->constrained('users')->nullOnDelete();
            $table->foreignUuid('decided_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
            $table->string('decision')->nullable()->after('is_approved_exception');
            $table->string('authority_policy_version')->nullable()->after('decision');
            $table->text('decision_notes')->nullable()->after('authority_policy_version');
            $table->timestamp('requested_at')->nullable()->after('due_at');
            $table->timestamp('decided_at')->nullable()->after('requested_at');

            $table->index(['reconciliation_id', 'status'], 'reconciliation_exceptions_reconciliation_status_index');
            $table->index(['status', 'requested_at'], 'reconciliation_exceptions_status_requested_index');
        });

        DB::table('reconciliation_exceptions')
            ->where('status', 'APPROVED_EXCEPTION')
            ->update([
                'requested_by' => DB::raw('COALESCE(resolved_by, owner_id)'),
                'decided_by' => DB::raw('resolved_by'),
                'decision' => 'APPROVED',
                'authority_policy_version' => 'legacy-v1',
                'requested_at' => DB::raw('COALESCE(resolved_at, updated_at)'),
                'decided_at' => DB::raw('COALESCE(resolved_at, updated_at)'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliation_exceptions', function (Blueprint $table) {
            $table->dropIndex('reconciliation_exceptions_reconciliation_status_index');
            $table->dropIndex('reconciliation_exceptions_status_requested_index');
            $table->dropConstrainedForeignId('requested_by');
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn([
                'decision',
                'authority_policy_version',
                'decision_notes',
                'requested_at',
                'decided_at',
            ]);
        });
    }
};
