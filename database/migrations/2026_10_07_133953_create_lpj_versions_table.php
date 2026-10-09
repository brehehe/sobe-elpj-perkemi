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
        Schema::create('lpj_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lpj_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('status');
            $table->json('snapshot');
            $table->string('checksum', 64);
            $table->boolean('is_immutable')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['lpj_id', 'version']);
            $table->index(['lpj_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lpj_versions');
    }
};
