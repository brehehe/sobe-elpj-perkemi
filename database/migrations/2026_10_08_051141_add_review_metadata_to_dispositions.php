<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dispositions', function (Blueprint $table) {
            $table->foreignUuid('reviewed_by')->nullable()->after('to_user_id')->constrained('users')->nullOnDelete();
            $table->json('evidence_ids')->nullable()->after('instructions');
            $table->text('response_notes')->nullable()->after('evidence_ids');
            $table->text('review_notes')->nullable()->after('response_notes');
            $table->timestamp('reviewed_at')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispositions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['evidence_ids', 'response_notes', 'review_notes', 'reviewed_at']);
        });
    }
};
