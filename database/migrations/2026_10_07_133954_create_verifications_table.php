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
        Schema::create('verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lpj_version_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('verifier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage');
            $table->string('status')->default('PENDING');
            $table->text('notes')->nullable();
            $table->json('checklist')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['lpj_version_id', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verifications');
    }
};
