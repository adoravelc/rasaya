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
        Schema::create('kategori_input_siswas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('input_id')->constrained('input_siswas')->cascadeOnDelete();
            $t->foreignId('kategori_id')->constrained('kategori_masalahs')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['input_id', 'kategori_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kategori_input_siswas');
    }
};
