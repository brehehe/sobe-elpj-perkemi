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
        Schema::create('approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lpj_version_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('decided_by')->constrained('users')->restrictOnDelete();
            $table->string('level');
            $table->string('decision');
            $table->text('notes')->nullable();
            $table->string('signature_hash', 64);
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->unique(['lpj_version_id', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
