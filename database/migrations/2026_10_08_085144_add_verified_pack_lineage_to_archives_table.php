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
        Schema::table('archives', function (Blueprint $table) {
            $table->foreignUuid('verified_pack_id')->nullable()->unique()->after('lpj_version_id')->constrained()->restrictOnDelete();
            $table->string('verified_pack_checksum', 64)->nullable()->after('checksum');
            $table->string('retention_policy_version')->nullable()->after('public_token');
            $table->unsignedSmallInteger('package_schema_version')->default(1)->after('retention_policy_version');
            $table->timestamp('integrity_verified_at')->nullable()->after('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->dropForeign(['verified_pack_id']);
            $table->dropColumn([
                'verified_pack_id',
                'verified_pack_checksum',
                'retention_policy_version',
                'package_schema_version',
                'integrity_verified_at',
            ]);
        });
    }
};
