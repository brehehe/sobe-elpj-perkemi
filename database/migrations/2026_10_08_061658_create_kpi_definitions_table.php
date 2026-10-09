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
        Schema::create('kpi_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('evaluation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name', 150);
            $table->text('definition');
            $table->string('unit', 50);
            $table->decimal('baseline', 18, 4)->nullable();
            $table->decimal('target', 18, 4);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('formula', 40);
            $table->string('formula_version', 50);
            $table->decimal('weight', 7, 4)->default(1);
            $table->timestamps();

            $table->unique(['evaluation_id', 'position']);
            $table->index(['name', 'unit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_definitions');
    }
};
