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
        Schema::table('ai_reviews', function (Blueprint $table) {
            $table->string('prompt_version', 50)->default('lpj-intelligence-v1')->after('prompt_hash');
            $table->string('response_id')->nullable()->after('output');
            $table->json('usage')->nullable()->after('response_id');
            $table->unsignedInteger('latency_ms')->nullable()->after('usage');
            $table->unsignedTinyInteger('retry_count')->default(0)->after('latency_ms');
            $table->text('review_notes')->nullable()->after('human_review_status');
            $table->json('reviewed_output')->nullable()->after('review_notes');

            $table->index(['human_review_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_reviews', function (Blueprint $table) {
            $table->dropIndex(['human_review_status', 'created_at']);
            $table->dropColumn([
                'prompt_version',
                'response_id',
                'usage',
                'latency_ms',
                'retry_count',
                'review_notes',
                'reviewed_output',
            ]);
        });
    }
};
