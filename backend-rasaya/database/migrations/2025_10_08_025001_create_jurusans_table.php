<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jurusans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans');
            $table->string('nama');
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['tahun_ajaran_id','nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurusans');
    }
};
