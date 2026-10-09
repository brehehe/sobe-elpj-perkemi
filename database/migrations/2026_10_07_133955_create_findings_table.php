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
        Schema::create('findings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lpj_version_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('severity')->default('MEDIUM');
            $table->string('status')->default('OPEN');
            $table->string('title');
            $table->text('detail');
            $table->date('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['lpj_version_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
