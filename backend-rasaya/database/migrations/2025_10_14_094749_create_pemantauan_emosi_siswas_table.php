<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pemantauan_emosi_siswas', function (Blueprint $t) {
            $t->id();

            // GANTI: pakai siswa_kelas_id -> refer ke siswa_kelass.id
            $t->foreignId('siswa_kelas_id')
                ->constrained('siswa_kelass')
                ->cascadeOnDelete();

            $t->date('tanggal')->index();
            $t->enum('sesi', ['pagi', 'sore'])->index();
            $t->unsignedTinyInteger('skor');
            $t->string('gambar')->nullable();
            $t->text('catatan')->nullable();

            $t->timestamps();
            $t->softDeletes();

            // unik per siswa_kelas_id + tanggal + sesi
            $t->unique(['siswa_kelas_id', 'tanggal', 'sesi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemantauan_emosi_siswas');
    }
};