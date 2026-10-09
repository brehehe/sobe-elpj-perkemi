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
        Schema::create('corrective_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('finding_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('action');
            $table->string('evidence_path')->nullable();
            $table->string('status')->default('SUBMITTED');
            $table->text('review_notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corrective_actions');
    }
};
