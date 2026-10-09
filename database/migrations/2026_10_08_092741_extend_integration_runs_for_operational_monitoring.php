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
        Schema::table('integration_runs', function (Blueprint $table) {
            $table->foreignUuid('unit_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignUuid('owner_id')->nullable()->after('unit_id')->constrained('users')->nullOnDelete();
            $table->foreignUuid('triggered_by')->nullable()->after('owner_id')->constrained('users')->nullOnDelete();
            $table->string('contract_version')->default('finance-transaction-v1')->after('direction');
            $table->uuid('correlation_id')->nullable()->after('idempotency_key');
            $table->string('error_category')->nullable()->after('last_error');
            $table->string('retry_status')->default('NOT_REQUIRED')->after('error_category');
            $table->unsignedInteger('records_received')->default(0)->after('attempts');
            $table->unsignedInteger('records_succeeded')->default(0)->after('records_received');
            $table->unsignedInteger('records_failed')->default(0)->after('records_succeeded');
            $table->unsignedInteger('duration_ms')->nullable()->after('records_failed');
            $table->timestamp('last_attempted_at')->nullable()->after('next_retry_at');

            $table->index(['unit_id', 'provider', 'status'], 'integration_runs_unit_provider_status_index');
            $table->index(['provider', 'last_attempted_at'], 'integration_runs_provider_attempt_index');
        });

        DB::table('integration_runs')->orderBy('created_at')->get()->each(function (object $run): void {
            $metadata = json_decode($run->request_metadata ?? '[]', true) ?: [];
            $unitId = isset($metadata['activity_id'])
                ? DB::table('activities')->where('id', $metadata['activity_id'])->value('unit_id')
                : null;
            $succeeded = $run->status === 'SUCCEEDED';
            $failed = in_array($run->status, ['FAILED', 'DEAD_LETTER'], true);

            DB::table('integration_runs')->where('id', $run->id)->update([
                'unit_id' => $unitId,
                'retry_status' => $run->status === 'DEAD_LETTER' ? 'EXHAUSTED' : 'NOT_REQUIRED',
                'records_received' => 1,
                'records_succeeded' => $succeeded ? 1 : 0,
                'records_failed' => $failed ? 1 : 0,
                'last_attempted_at' => $run->updated_at,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integration_runs', function (Blueprint $table) {
            $table->dropIndex('integration_runs_unit_provider_status_index');
            $table->dropIndex('integration_runs_provider_attempt_index');
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('owner_id');
            $table->dropConstrainedForeignId('triggered_by');
            $table->dropColumn([
                'contract_version',
                'correlation_id',
                'error_category',
                'retry_status',
                'records_received',
                'records_succeeded',
                'records_failed',
                'duration_ms',
                'last_attempted_at',
            ]);
        });
    }
};
