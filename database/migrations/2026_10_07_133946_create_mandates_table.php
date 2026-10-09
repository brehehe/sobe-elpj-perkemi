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
        Schema::create('mandates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('issued_at');
            $table->date('expires_at')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->json('source_metadata')->nullable();
            $table->timestamps();

            $table->unique(['unit_id', 'number']);
            $table->index(['unit_id', 'status', 'issued_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mandates');
    }
};
