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
        Schema::table('evidence', function (Blueprint $table) {
            $table->foreignUuid('supersedes_id')->nullable()->after('activity_id')->constrained('evidence')->nullOnDelete();
            $table->unsignedInteger('version')->default(1)->after('type');
            $table->string('scan_status')->default('REGISTERED')->after('sha256');
            $table->string('review_status')->default('PENDING')->after('scan_status');
            $table->string('access_classification')->default('INTERNAL')->after('review_status');
            $table->index(['activity_id', 'review_status', 'type']);
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->foreignUuid('owner_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->text('criteria')->nullable()->after('title');
            $table->text('condition')->nullable()->after('criteria');
            $table->text('cause')->nullable()->after('condition');
            $table->text('risk')->nullable()->after('cause');
            $table->text('recommendation')->nullable()->after('risk');
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->uuid('event_id')->nullable()->unique()->after('id');
            $table->uuid('correlation_id')->nullable()->after('event_id');
            $table->string('source_channel')->default('WEB')->after('event_type');
            $table->string('result')->default('SUCCEEDED')->after('to_status');
            $table->index(['correlation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropIndex(['correlation_id', 'created_at']);
            $table->dropUnique(['event_id']);
            $table->dropColumn(['event_id', 'correlation_id', 'source_channel', 'result']);
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn(['criteria', 'condition', 'cause', 'risk', 'recommendation']);
        });

        Schema::table('evidence', function (Blueprint $table) {
            $table->dropIndex(['activity_id', 'review_status', 'type']);
            $table->dropConstrainedForeignId('supersedes_id');
            $table->dropColumn(['version', 'scan_status', 'review_status', 'access_classification']);
        });
    }
};
