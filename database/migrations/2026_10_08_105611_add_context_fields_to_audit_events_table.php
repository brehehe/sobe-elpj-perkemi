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
        Schema::table('audit_events', function (Blueprint $table) {
            $table->string('request_id', 100)->nullable()->after('correlation_id');
            $table->string('object_version', 50)->nullable()->after('auditable_id');
            $table->string('authority')->nullable()->after('source_channel');
            $table->unsignedSmallInteger('payload_version')->default(1)->after('authority');
            $table->json('evidence_references')->nullable()->after('payload');

            $table->index(['request_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropIndex(['request_id', 'created_at']);
            $table->dropColumn([
                'request_id',
                'object_version',
                'authority',
                'payload_version',
                'evidence_references',
            ]);
        });
    }
};
