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
            // Relasi ke jurusan (nullable = tanpa jurusan)
            $t->foreignId('jurusan_id')->nullable()->constrained('jurusans')->nullOnDelete();
            $t->unsignedTinyInteger('rombel');        // 1..n

            // generated column untuk unique saat jurusan_id null
            // (kalau MariaDB keberatan dengan virtual, bisa diganti storedAs)
            $t->unsignedBigInteger('jurusan_key')->virtualAs('COALESCE(jurusan_id, 0)');

            // FK wali guru (nullable)
            $t->foreignId('wali_guru_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // unique kombinasi per tahun ajaran
            $t->unique(['tahun_ajaran_id', 'tingkat', 'jurusan_key', 'rombel']);

            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa_kelass');
        Schema::dropIfExists('kelass');
    }
};
