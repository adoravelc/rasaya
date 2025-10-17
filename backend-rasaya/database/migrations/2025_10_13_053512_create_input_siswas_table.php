<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('input_siswas', function (Blueprint $t) {
            $t->id();

            // GANTI: pakai siswa_kelas_id (pelapor)
            $t->foreignId('siswa_kelas_id')
                ->constrained('siswa_kelass')
                ->cascadeOnDelete();

            // Opsional: yang dilaporkan (juga siswa_kelas)
            $t->foreignId('siswa_dilapor_kelas_id')
                ->nullable()
                ->constrained('siswa_kelass')
                ->nullOnDelete();

            $t->date('tanggal')->index();                   // default di controller = today
            $t->text('teks');                               // isi refleksi
            $t->decimal('avg_emosi', 3, 1)->nullable();     // 0..10 (opsional)
            $t->string('gambar')->nullable();               // path bukti (opsional)
            $t->tinyInteger('status_upload')->default(0);   // 0 belum, 1 sukses, dst (opsional)
            $t->json('meta')->nullable();                   // info tambahan
            $t->timestamps();
            $t->softDeletes();

            // unik per hari per siswa_kelas
            $t->unique(['siswa_kelas_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('input_siswas');
    }
};