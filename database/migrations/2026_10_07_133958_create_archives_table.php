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
        Schema::create('archives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lpj_version_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('archived_by')->constrained('users')->restrictOnDelete();
            $table->string('archive_number')->unique();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('checksum', 64);
            $table->uuid('public_token')->unique();
            $table->timestamp('archived_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archives');
    }
};
