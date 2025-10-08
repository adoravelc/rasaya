<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kelass', function (Blueprint $t) {
            $t->id();

            $t->foreignId('tahun_ajaran_id')
                ->constrained('tahun_ajarans')
                ->cascadeOnDelete();

            $t->enum('tingkat', ['X', 'XI', 'XII']);
            $t->string('penjurusan')->nullable();     // null = tanpa jurusan
            $t->unsignedTinyInteger('rombel');        // 1..n

            // generated column untuk unique saat penjurusan null
            // (kalau MariaDB kamu keberatan dengan virtual, ganti ke ->storedAs(...))
            $t->string('penjurusan_key')->virtualAs("COALESCE(penjurusan, '-')");

            // FK wali guru (nullable)
            $t->foreignId('wali_guru_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // unique kombinasi per tahun ajaran
            $t->unique(['tahun_ajaran_id', 'tingkat', 'penjurusan_key', 'rombel']);

            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelass');
    }
};
