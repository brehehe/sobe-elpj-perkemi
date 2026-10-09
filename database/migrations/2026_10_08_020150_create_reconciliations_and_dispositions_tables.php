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
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('financial_transaction_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('budget_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('PENDING');
            $table->decimal('expected_amount', 18, 2)->default(0);
            $table->decimal('actual_amount', 18, 2)->default(0);
            $table->decimal('variance', 18, 2)->default(0);
            $table->json('checks')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'reconciled_at']);
        });

        Schema::create('reconciliation_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reconciliation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category');
            $table->string('severity')->default('MEDIUM');
            $table->string('status')->default('OPEN');
            $table->text('detail');
            $table->string('resolution_type')->nullable();
            $table->text('resolution')->nullable();
            $table->boolean('is_approved_exception')->default(false);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index(['owner_id', 'status']);
        });

        Schema::create('dispositions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lpj_version_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('unit_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('from_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('to_role')->nullable();
            $table->string('subject');
            $table->text('instructions');
            $table->string('priority')->default('MEDIUM');
            $table->string('status')->default('OPEN');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['lpj_version_id', 'status']);
            $table->index(['to_user_id', 'status', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispositions');
        Schema::dropIfExists('reconciliation_exceptions');
        Schema::dropIfExists('reconciliations');
    }
};
