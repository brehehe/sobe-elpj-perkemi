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
        Schema::create('activity_agenda_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('PLANNED');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['activity_id', 'status', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_agenda_items');
    }
};
