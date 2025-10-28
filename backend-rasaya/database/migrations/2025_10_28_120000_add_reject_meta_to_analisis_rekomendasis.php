<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('analisis_rekomendasis', function (Blueprint $t) {
            $t->foreignId('rejected_kategori_id')->nullable()
                ->after('status')
                ->constrained('kategori_masalahs')->nullOnDelete();

            $t->foreignId('selected_master_rekomendasi_id')->nullable()
                ->after('rejected_kategori_id')
                ->constrained('master_rekomendasis')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('analisis_rekomendasis', function (Blueprint $t) {
            $t->dropConstrainedForeignId('selected_master_rekomendasi_id');
            $t->dropConstrainedForeignId('rejected_kategori_id');
        });
    }
};
