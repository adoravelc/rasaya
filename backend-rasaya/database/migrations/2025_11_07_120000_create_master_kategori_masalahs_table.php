<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('master_kategori_masalahs', function (Blueprint $t) {
            $t->id();
            $t->string('kode')->unique(); // e.g. MENTAL_EMOSI
            $t->string('nama');
            $t->string('deskripsi')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_kategori_masalahs');
    }
};
