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
        Schema::create('verified_packs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lpj_version_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUuid('approval_id')->unique()->constrained('approvals')->restrictOnDelete();
            $table->foreignUuid('issued_by')->constrained('users')->restrictOnDelete();
            $table->string('verification_number')->unique();
            $table->string('policy_version');
            $table->unsignedSmallInteger('schema_version');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('checksum', 64);
            $table->string('lpj_checksum', 64);
            $table->string('approval_signature_hash', 64);
            $table->uuid('public_token')->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index('issued_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verified_packs');
    }
};
