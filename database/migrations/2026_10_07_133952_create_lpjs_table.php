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
        Schema::create('lpjs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('DRAFT');
            $table->unsignedInteger('current_version')->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('pb_verified_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lpjs');
    }
};
