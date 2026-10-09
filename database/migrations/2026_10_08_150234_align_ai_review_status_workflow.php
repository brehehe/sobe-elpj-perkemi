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
        DB::table('ai_reviews')
            ->where('status', 'PENDING')
            ->update(['status' => 'QUEUED']);
        DB::table('ai_reviews')
            ->where('status', 'COMPLETED')
            ->whereIn('human_review_status', ['ACCEPTED', 'MODIFIED'])
            ->update(['status' => 'SUCCEEDED']);
        DB::table('ai_reviews')
            ->where('status', 'COMPLETED')
            ->where('human_review_status', 'REJECTED')
            ->update(['status' => 'CANCELLED']);
        DB::table('ai_reviews')
            ->where('status', 'COMPLETED')
            ->update(['status' => 'REVIEW_REQUIRED']);

        Schema::table('ai_reviews', function (Blueprint $table) {
            $table->string('status')->default('QUEUED')->change();
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ai_reviews')
            ->whereIn('status', ['QUEUED', 'RUNNING'])
            ->update(['status' => 'PENDING']);
        DB::table('ai_reviews')
            ->whereIn('status', ['REVIEW_REQUIRED', 'SUCCEEDED', 'CANCELLED'])
            ->update(['status' => 'COMPLETED']);

        Schema::table('ai_reviews', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->string('status')->default('PENDING')->change();
        });
    }
};
