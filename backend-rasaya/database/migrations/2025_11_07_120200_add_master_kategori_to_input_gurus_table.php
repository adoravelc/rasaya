<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('input_gurus', function (Blueprint $t) {
            $t->foreignId('master_kategori_masalah_id')
                ->nullable()
                ->after('siswa_kelas_id')
                ->constrained('master_kategori_masalahs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('input_gurus', function (Blueprint $t) {
            $t->dropConstrainedForeignId('master_kategori_masalah_id');
        });
    }
};
