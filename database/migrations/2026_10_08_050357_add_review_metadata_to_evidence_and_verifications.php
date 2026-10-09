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
        Schema::table('evidence', function (Blueprint $table) {
            $table->foreignUuid('reviewed_by')->nullable()->after('uploaded_by')->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable()->after('review_status');
            $table->timestamp('reviewed_at')->nullable()->after('review_notes');
        });

        Schema::table('verifications', function (Blueprint $table) {
            $table->json('evidence_ids')->nullable()->after('checklist');
            $table->text('material_exception')->nullable()->after('evidence_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->dropColumn(['evidence_ids', 'material_exception']);
        });

        Schema::table('evidence', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_notes', 'reviewed_at']);
        });
    }
};
