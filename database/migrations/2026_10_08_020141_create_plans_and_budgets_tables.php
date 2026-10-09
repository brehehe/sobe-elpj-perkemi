<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mandate_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('unit_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('status')->default('DRAFT');
            $table->unsignedInteger('current_version')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['unit_id', 'status', 'updated_at']);
            $table->index(['mandate_id', 'status']);
        });

        Schema::create('plan_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('DRAFT');
            $table->string('activity_type');
            $table->text('objective');
            $table->text('output');
            $table->text('outcome');
            $table->unsignedInteger('target_participants');
            $table->json('kpis');
            $table->json('schedule')->nullable();
            $table->json('resources')->nullable();
            $table->json('risks')->nullable();
            $table->text('change_reason')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('checksum', 64);
            $table->boolean('is_immutable')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'version']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUuid('unit_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('status')->default('DRAFT');
            $table->unsignedInteger('current_version')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['unit_id', 'status', 'updated_at']);
        });

        Schema::create('budget_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('DRAFT');
            $table->decimal('total', 18, 2)->default(0);
            $table->text('change_reason')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('checksum', 64);
            $table->boolean('is_immutable')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['budget_id', 'version']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('budget_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('account_code');
            $table->string('description');
            $table->decimal('quantity', 14, 2);
            $table->string('unit', 50);
            $table->decimal('unit_cost', 18, 2);
            $table->decimal('subtotal', 18, 2);
            $table->timestamps();

            $table->unique(['budget_version_id', 'line_number']);
            $table->index(['account_code', 'created_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE plan_versions ADD CONSTRAINT plan_versions_target_participants_check CHECK (target_participants > 0)');
            DB::statement('ALTER TABLE budget_versions ADD CONSTRAINT budget_versions_total_check CHECK (total >= 0)');
            DB::statement('ALTER TABLE budget_lines ADD CONSTRAINT budget_lines_quantity_check CHECK (quantity > 0)');
            DB::statement('ALTER TABLE budget_lines ADD CONSTRAINT budget_lines_unit_cost_check CHECK (unit_cost >= 0)');
            DB::statement('ALTER TABLE budget_lines ADD CONSTRAINT budget_lines_subtotal_check CHECK (subtotal >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budget_versions');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('plan_versions');
        Schema::dropIfExists('plans');
    }
};
