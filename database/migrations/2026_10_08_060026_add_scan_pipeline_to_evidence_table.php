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
        Schema::table('evidence', function (Blueprint $table) {
            $table->string('detected_mime_type')->nullable()->after('mime_type');
            $table->string('scan_engine')->nullable()->after('scan_status');
            $table->text('scan_notes')->nullable()->after('scan_engine');
            $table->timestamp('scanned_at')->nullable()->after('scan_notes');
            $table->timestamp('linked_at')->nullable()->after('scanned_at');
            $table->index(['scan_status', 'created_at']);
            $table->string('scan_status')->default('UPLOADING')->change();
        });

        DB::table('evidence')
            ->where('scan_status', 'REGISTERED')
            ->update([
                'detected_mime_type' => DB::raw('mime_type'),
                'scan_engine' => 'LEGACY',
                'scan_notes' => 'Bukti terdaftar sebelum pipeline pemindaian diterapkan.',
                'scanned_at' => DB::raw('created_at'),
                'linked_at' => DB::raw('created_at'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evidence', function (Blueprint $table) {
            $table->dropIndex(['scan_status', 'created_at']);
            $table->dropColumn(['detected_mime_type', 'scan_engine', 'scan_notes', 'scanned_at', 'linked_at']);
            $table->string('scan_status')->default('REGISTERED')->change();
        });
    }
};
