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
        Schema::create('ai_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('lpj_version_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('PENDING');
            $table->string('model')->nullable();
            $table->string('prompt_hash', 64);
            $table->json('input_sources');
            $table->json('output')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->text('limitations')->nullable();
            $table->string('human_review_status')->default('PENDING');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['activity_id', 'type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_reviews');
    }
};
