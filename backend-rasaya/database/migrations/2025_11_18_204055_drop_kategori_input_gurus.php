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
        Schema::dropIfExists('kategori_input_gurus');
    }

    public function down(): void
    {
        if (!Schema::hasTable('kategori_input_gurus')) {
            Schema::create('kategori_input_gurus', function (Blueprint $t) {
                $t->id();
                // Relasi ke input_gurus
                $t->foreignId('input_id')->constrained('input_gurus')->cascadeOnDelete();
                // Asumsi relasi ke master kategori (kategori besar)
                $t->foreignId('master_kategori_id')->constrained('master_kategori_masalahs')->cascadeOnDelete();
                $t->timestamps();
                $t->unique(['input_id', 'master_kategori_id']);
            });
        }
    }
};
