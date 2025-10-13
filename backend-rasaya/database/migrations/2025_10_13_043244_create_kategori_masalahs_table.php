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
        Schema::create('kategori_masalahs', function (Blueprint $t) {
            $t->id();
            $t->string('kode')->unique(); // “AKD”, “EMO”, “SOS”, dst
            $t->string('nama');                 // “Akademik”, “Emosi”, “Sosial”, dst
            $t->string('deskripsi')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kategori_masalahs');
    }
};
