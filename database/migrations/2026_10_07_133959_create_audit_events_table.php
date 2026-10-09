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
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableUuidMorphs('auditable');
            $table->string('event_type');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('payload')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['unit_id', 'event_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
