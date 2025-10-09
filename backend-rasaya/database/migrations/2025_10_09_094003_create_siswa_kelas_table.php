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
        Schema::create('siswa_kelass', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans')->cascadeOnDelete();
            $t->foreignId('kelas_id')->constrained('kelass')->cascadeOnDelete();

            $t->foreignId('siswa_id')
                ->constrained('siswas', 'user_id')
                ->cascadeOnDelete();

            $t->boolean('is_active')->default(true);
            $t->date('joined_at')->nullable();
            $t->date('left_at')->nullable();

            $t->timestamps();

            $t->unique(['tahun_ajaran_id', 'kelas_id', 'siswa_id']);
            $t->index(['kelas_id', 'is_active']);
            $t->index(['siswa_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siswa_kelas');
    }
};
