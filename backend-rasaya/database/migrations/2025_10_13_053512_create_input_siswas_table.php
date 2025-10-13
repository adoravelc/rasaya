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
            $t->foreignId('siswa_id');
            $t->foreign('siswa_id')->references('user_id')->on('siswas')->cascadeOnDelete();
            $t->text('teks');
            $t->decimal('avg_emosi', 3, 1)->nullable();   // ← jadikan nullable (0..10 di-validasi di Request)
            $t->json('meta')->nullable();  // opsional (device, versi app, dsb)
            $t->date('tanggal'); // Tambahkan ini sebelum unique dan index
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['siswa_id', 'tanggal']);          // 1x per hari per siswa
            $t->index(['siswa_id', 'tanggal']);           // bantu query histori
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
