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
        Schema::table('smart_tasks', function (Blueprint $table) {
            $table->string('subject_type')->nullable()->after('activity_id');
            $table->uuid('subject_id')->nullable()->after('subject_type');
            $table->string('recipient_permission')->nullable()->after('assignee_id');
            $table->text('completion_condition')->nullable()->after('description');
            $table->string('deduplication_key')->nullable()->unique()->after('source');
            $table->string('escalation_path')->nullable()->after('due_at');
            $table->timestamp('escalation_at')->nullable()->after('escalation_path');
            $table->timestamp('started_at')->nullable()->after('escalation_at');
            $table->timestamp('completed_at')->nullable()->after('started_at');

            $table->index(['subject_type', 'subject_id']);
            $table->index(['recipient_permission', 'status', 'due_at'], 'smart_tasks_recipient_status_due_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smart_tasks', function (Blueprint $table) {
            $table->dropUnique(['deduplication_key']);
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropIndex('smart_tasks_recipient_status_due_index');
            $table->dropColumn([
                'subject_type',
                'subject_id',
                'recipient_permission',
                'completion_condition',
                'deduplication_key',
                'escalation_path',
                'escalation_at',
                'started_at',
                'completed_at',
            ]);
        });
    }
};
