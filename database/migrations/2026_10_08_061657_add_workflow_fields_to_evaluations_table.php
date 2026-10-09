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
        Schema::table('evaluations', function (Blueprint $table) {
            $table->foreignUuid('supersedes_id')->nullable()->after('id')->constrained('evaluations')->nullOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1)->after('reviewed_by');
            $table->string('status', 32)->default('ACCEPTED')->after('version');
            $table->decimal('completion_percent', 5, 2)->nullable()->after('outcomes');
            $table->decimal('satisfaction_score', 4, 2)->nullable()->after('completion_percent');
            $table->text('lessons_learned')->nullable()->after('satisfaction_score');
            $table->text('successful_practices')->nullable()->after('lessons_learned');
            $table->text('improvement_areas')->nullable()->after('successful_practices');
            $table->string('formula_version', 50)->nullable()->after('recommendations');
            $table->text('review_notes')->nullable()->after('formula_version');
            $table->timestampTz('reviewed_at')->nullable()->after('review_notes');
            $table->timestampTz('accepted_at')->nullable()->after('reviewed_at');

        });

        DB::table('evaluations')->update([
            'status' => 'ACCEPTED',
            'formula_version' => 'legacy-manual-v0',
            'accepted_at' => DB::raw('created_at'),
        ]);

        $versionsByActivity = [];
        DB::table('evaluations')
            ->orderBy('activity_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'activity_id'])
            ->each(function (object $evaluation) use (&$versionsByActivity): void {
                $version = ($versionsByActivity[$evaluation->activity_id] ?? 0) + 1;
                $versionsByActivity[$evaluation->activity_id] = $version;
                DB::table('evaluations')->where('id', $evaluation->id)->update(['version' => $version]);
            });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->unique(['activity_id', 'version']);
            $table->index(['activity_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropUnique(['activity_id', 'version']);
            $table->dropIndex(['activity_id', 'status']);
            $table->dropConstrainedForeignId('supersedes_id');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'version', 'status', 'completion_percent', 'satisfaction_score',
                'lessons_learned', 'successful_practices', 'improvement_areas',
                'formula_version', 'review_notes', 'reviewed_at', 'accepted_at',
            ]);
        });
    }
};
