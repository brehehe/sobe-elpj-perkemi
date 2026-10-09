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
        Schema::create('evidence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('type');
            $table->string('title');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->timestamp('captured_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['activity_id', 'type']);
            $table->index('sha256');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evidence');
    }
};
