<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('analisis_rekomendasis', function (Blueprint $table) {
            // Add kategori_masalah_id if not exists
            if (!Schema::hasColumn('analisis_rekomendasis', 'kategori_masalah_id')) {
                $table->unsignedBigInteger('kategori_masalah_id')->nullable()->after('master_rekomendasi_id');
                $table->foreign('kategori_masalah_id')->references('id')->on('kategori_masalahs')->onDelete('set null');
            }
            
            // Add rules column if not exists
            if (!Schema::hasColumn('analisis_rekomendasis', 'rules')) {
                $table->json('rules')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analisis_rekomendasis', function (Blueprint $table) {
            if (Schema::hasColumn('analisis_rekomendasis', 'kategori_masalah_id')) {
                $table->dropForeign(['kategori_masalah_id']);
                $table->dropColumn('kategori_masalah_id');
            }
            
            if (Schema::hasColumn('analisis_rekomendasis', 'rules')) {
                $table->dropColumn('rules');
            }
        });
    }
};
