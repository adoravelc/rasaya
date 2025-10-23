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
        Schema::create('analisis_entries', function (Blueprint $t) {
            $t->id();

            // refer ke siswa_kelas (bukan siswas.user_id lagi)
            $t->foreignId('siswa_kelas_id')
                ->constrained('siswa_kelas')   // FK -> siswa_kelas.id
                ->cascadeOnDelete();

            // hasil analisis
            $t->double('skor_sentimen')->nullable(); // -1..1 / 0..1 terserah engine, fleksibel
            $t->json('kata_kunci')->nullable();      // array kata kunci / top terms

            // sumber data (siapa yang dianalisis)
            $t->enum('source', ['input_siswa', 'input_guru'])
              ->index();                             // contoh: "input_siswa" (refleksi), "input_guru" (observasi)
            $t->unsignedBigInteger('source_id')->index(); // id dari tabel sumber di atas

            // waktu proses (opsional tapi berguna untuk audit/monitoring)
            $t->dateTime('tanggal_awal_proses')->nullable();
            $t->dateTime('tanggal_akhir_proses')->nullable();

            $t->timestamps();

            // index gabungan umum untuk query cepat per siswa & rentang waktu
            $t->index(['siswa_kelas_id', 'tanggal_awal_proses']);
            $t->index(['siswa_kelas_id', 'tanggal_akhir_proses']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analisis_entries');
    }
};
