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
        Schema::create('input_siswas', function (Blueprint $t) {
            $t->id();

            // Catatan: di proyekmu, relasi Siswa keyed ke user_id (bukan id)
            $t->unsignedBigInteger('siswa_id');             // pelapor (pemilik entri)
            $t->unsignedBigInteger('siswa_dilapor_id')      // korban/teman yang dilaporkan (opsional)
                ->nullable();

            $t->date('tanggal')->index();                   // default di controller = today
            $t->text('teks');                               // isi refleksi
            $t->decimal('avg_emosi', 3, 1)->nullable();     // 0..10 (opsional)
            $t->string('gambar')->nullable();               // path bukti (opsional)
            $t->tinyInteger('status_upload')->default(0);   // 0 belum, 1 sukses, dst (opsional)
            $t->json('meta')->nullable();                   // info tambahan
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['siswa_id', 'tanggal']);

            // FK -> siswas.user_id (sesuai skema kamu)
            $t->foreign('siswa_id')
                ->references('user_id')->on('siswas')->cascadeOnDelete();

            $t->foreign('siswa_dilapor_id')
                ->references('user_id')->on('siswas')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('input_siswas');
    }
};
