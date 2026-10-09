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
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignUuid('plan_id')->nullable()->after('mandate_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('budget_id')->nullable()->after('plan_id')->constrained()->restrictOnDelete();
            $table->index(['plan_id', 'budget_id']);
        });

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->foreignUuid('budget_line_id')->nullable()->after('activity_id')->constrained()->nullOnDelete();
            $table->string('counterparty')->nullable()->after('description');
            $table->string('payment_method')->nullable()->after('counterparty');
            $table->string('reconciliation_status')->default('PENDING')->after('status');
            $table->index(['activity_id', 'reconciliation_status', 'transaction_date'], 'transactions_reconciliation_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_reconciliation_index');
            $table->dropConstrainedForeignId('budget_line_id');
            $table->dropColumn(['counterparty', 'payment_method', 'reconciliation_status']);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['plan_id', 'budget_id']);
            $table->dropConstrainedForeignId('budget_id');
            $table->dropConstrainedForeignId('plan_id');
        });
    }
};
