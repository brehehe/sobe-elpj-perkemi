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
        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('mandate_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('pic_id')->constrained('users')->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('PLANNED');
            $table->unsignedInteger('plan_version')->default(1);
            $table->unsignedInteger('budget_version')->default(1);
            $table->decimal('budget_ceiling', 18, 2)->default(0);
            $table->json('plan_snapshot')->nullable();
            $table->json('budget_snapshot')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'status', 'starts_at']);
            $table->index(['pic_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
