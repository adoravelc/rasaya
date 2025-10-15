<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('input_gurus', function (Blueprint $t) {
            $t->id();

            // pelapor (guru) -> ref gurus.user_id (seperti skema kamu)
            $t->foreignId('guru_id');
            $t->foreign('guru_id')->references('user_id')->on('gurus')->cascadeOnDelete();

            // TARGET sekarang via roster (siswa_kelass.id)
            $t->foreignId('siswa_kelas_id');
            $t->foreign('siswa_kelas_id')->references('id')->on('siswa_kelass')->cascadeOnDelete();

            $t->date('tanggal')->index();
            $t->text('teks');
            $t->string('gambar')->nullable();

            $t->enum('kondisi_siswa', ['green', 'yellow', 'orange', 'red', 'black', 'grey'])->index();

            $t->timestamps();
            $t->softDeletes();

            // cegah duplikasi per (guru, roster, tanggal)
            $t->unique(['guru_id', 'siswa_kelas_id', 'tanggal']);
        });

        Schema::create('kategori_input_gurus', function (Blueprint $t) {
            $t->id();
            $t->foreignId('input_guru_id')->constrained('input_gurus')->cascadeOnDelete();
            $t->foreignId('kategori_id')->constrained('kategori_masalahs')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['input_guru_id', 'kategori_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('input_gurus');
    }
};
