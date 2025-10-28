<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // In case a previous failed attempt created the table without FKs, drop and recreate cleanly
        Schema::dropIfExists('kategori_masalah_master_rekomendasi');

        Schema::create('kategori_masalah_master_rekomendasi', function (Blueprint $t) {
            $t->unsignedBigInteger('kategori_masalah_id');
            $t->unsignedBigInteger('master_rekomendasi_id');

            // Short, explicit FK names to avoid MySQL 64-char limit
            $t->foreign('kategori_masalah_id', 'km_mr_kat_fk')
                ->references('id')->on('kategori_masalahs')->cascadeOnDelete();
            $t->foreign('master_rekomendasi_id', 'km_mr_master_fk')
                ->references('id')->on('master_rekomendasis')->cascadeOnDelete();

            $t->primary(['kategori_masalah_id', 'master_rekomendasi_id'], 'km_mr_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori_masalah_master_rekomendasi');
    }
};
