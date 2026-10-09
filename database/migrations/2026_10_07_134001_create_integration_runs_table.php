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
        Schema::create('integration_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider');
            $table->string('direction')->default('INBOUND');
            $table->string('idempotency_key')->unique();
            $table->string('status')->default('PENDING');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('payload_hash', 64);
            $table->json('request_metadata')->nullable();
            $table->json('response_metadata')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status', 'next_retry_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_runs');
    }
};
