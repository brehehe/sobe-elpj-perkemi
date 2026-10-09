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
        Schema::create('kpi_catalogs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_taxonomy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->text('definition');
            $table->string('unit', 50);
            $table->string('formula', 30);
            $table->decimal('default_weight', 5, 2)->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_catalogs');
    }
};
