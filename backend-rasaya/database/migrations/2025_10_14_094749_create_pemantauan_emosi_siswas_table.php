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
        Schema::create('pemantauan_emosi_siswas', function (Blueprint $t) {
            $t->id();
            // tetap: refer ke siswas.user_id
            $t->foreignId('siswa_id');
            $t->foreign('siswa_id')->references('user_id')->on('siswas')->cascadeOnDelete();
            $t->date('tanggal')->index();                 // dipakai di unique
            $t->enum('sesi', ['pagi', 'sore'])->index();  // ditentukan sistem (controller)
            $t->unsignedTinyInteger('skor');
            $t->string('gambar')->nullable();             
            $t->timestamps();
            $t->softDeletes();                            

            $t->unique(['siswa_id', 'tanggal', 'sesi']);  // 1 entri per hari per sesi
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pemantauan_emosi_siswas');
    }
};
