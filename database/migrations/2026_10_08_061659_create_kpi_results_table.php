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
        Schema::create('kpi_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kpi_definition_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('evidence_id')->constrained('evidence')->restrictOnDelete();
            $table->decimal('actual', 18, 4);
            $table->decimal('achievement_percent', 7, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['evidence_id', 'achievement_percent']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_results');
    }
};
