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
        Schema::table('lpjs', function (Blueprint $table) {
            $table->text('executive_summary')->nullable()->after('total_amount');
            $table->text('implementation_narrative')->nullable()->after('executive_summary');
            $table->text('outcome_narrative')->nullable()->after('implementation_narrative');
            $table->text('financial_narrative')->nullable()->after('outcome_narrative');
            $table->text('constraints')->nullable()->after('financial_narrative');
            $table->text('follow_up_plan')->nullable()->after('constraints');
            $table->unsignedInteger('draft_revision')->default(0)->after('follow_up_plan');
            $table->foreignUuid('draft_updated_by')->nullable()->after('draft_revision')->constrained('users')->nullOnDelete();
            $table->timestamp('draft_updated_at')->nullable()->after('draft_updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lpjs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('draft_updated_by');
            $table->dropColumn([
                'executive_summary',
                'implementation_narrative',
                'outcome_narrative',
                'financial_narrative',
                'constraints',
                'follow_up_plan',
                'draft_revision',
                'draft_updated_at',
            ]);
        });
    }
};
