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
        Schema::table('plans', function (Blueprint $table) {
            $table->foreignUuid('source_ai_review_id')
                ->nullable()
                ->unique()
                ->after('owner_id')
                ->constrained('ai_reviews')
                ->restrictOnDelete();
        });

        Schema::table('plan_versions', function (Blueprint $table) {
            $table->json('source_context')->nullable()->after('risks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_versions', function (Blueprint $table) {
            $table->dropColumn('source_context');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_ai_review_id');
        });
    }
};
