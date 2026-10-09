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
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('MANUAL');
            $table->string('external_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('account_code')->nullable();
            $table->string('type')->default('EXPENSE');
            $table->text('description');
            $table->date('transaction_date');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->default('IDR');
            $table->string('status')->default('POSTED');
            $table->json('source_payload')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['activity_id', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
