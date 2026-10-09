<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_taxonomies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->json('comparable_factors');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $now = now();
        DB::table('activity_taxonomies')->insert(collect([
            ['code' => 'PENATARAN', 'name' => 'Penataran', 'factors' => ['level/tema', 'durasi', 'format', 'jumlah peserta']],
            ['code' => 'KEJUARAAN', 'name' => 'Kejuaraan', 'factors' => ['kelas pertandingan', 'jumlah peserta', 'venue', 'durasi']],
            ['code' => 'RAPAT', 'name' => 'Raker/Rapat', 'factors' => ['tingkat organisasi', 'jumlah agenda', 'jumlah peserta', 'durasi']],
            ['code' => 'PEMBINAAN', 'name' => 'Pembinaan', 'factors' => ['sasaran pembinaan', 'durasi', 'jumlah peserta', 'output']],
            ['code' => 'UJIAN', 'name' => 'Ujian', 'factors' => ['tingkat ujian', 'jumlah peserta', 'jumlah penguji', 'durasi']],
            ['code' => 'LAINNYA', 'name' => 'Lainnya', 'factors' => ['output', 'periode', 'jumlah peserta', 'durasi']],
        ])->map(fn (array $taxonomy): array => [
            'id' => (string) Str::uuid7(),
            'code' => $taxonomy['code'],
            'name' => $taxonomy['name'],
            'description' => 'Taksonomi bawaan untuk klasifikasi planning dan analisis kegiatan.',
            'comparable_factors' => json_encode($taxonomy['factors'], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_taxonomies');
    }
};
