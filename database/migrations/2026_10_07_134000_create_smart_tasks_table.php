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
        Schema::create('smart_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('activity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('MEDIUM');
            $table->string('status')->default('OPEN');
            $table->string('source')->default('SYSTEM');
            $table->timestamp('due_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'status', 'due_at']);
            $table->index(['assignee_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smart_tasks');
    }
};
