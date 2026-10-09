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
        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('granted_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('level');
            $table->decimal('max_amount', 18, 2)->nullable();
            $table->string('reference');
            $table->text('notes')->nullable();
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['delegate_id', 'level', 'valid_from', 'valid_until'], 'approval_delegations_active_index');
            $table->index(['unit_id', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_delegations');
    }
};
