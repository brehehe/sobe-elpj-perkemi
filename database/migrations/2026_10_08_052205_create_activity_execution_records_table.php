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
        Schema::create('activity_execution_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('details')->nullable();
            $table->string('severity')->nullable();
            $table->unsignedTinyInteger('progress_percent')->nullable();
            $table->string('status')->default('RECORDED');
            $table->timestamp('occurred_at');
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['activity_id', 'type', 'status', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_execution_records');
    }
};
