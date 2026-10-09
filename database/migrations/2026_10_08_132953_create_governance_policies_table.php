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
        Schema::create('governance_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 30);
            $table->string('version', 100);
            $table->json('settings');
            $table->text('change_summary');
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at');
            $table->timestamps();

            $table->unique(['type', 'version']);
            $table->index(['type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('governance_policies');
    }
};
