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
        Schema::table('slot_konselings', function (Blueprint $table) {
            // Flag slot private (hanya untuk satu siswa)
            $table->boolean('is_private')->default(false)->after('status');
            // Target siswa_kelas (booking khusus); nullable sampai dijadwalkan
            $table->foreignId('target_siswa_kelas_id')->nullable()
                ->constrained('siswa_kelass')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slot_konselings', function (Blueprint $table) {
            // Rollback private slot fields
            $table->dropForeign(['target_siswa_kelas_id']);
            $table->dropColumn(['is_private', 'target_siswa_kelas_id']);
        });
    }
};
