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
        Schema::table('kategori_masalahs', function (Blueprint $table) {
            $table->json('kata_kunci')->nullable()->after('deskripsi');
        });

        Schema::table('master_kategori_masalahs', function (Blueprint $table) {
            $table->json('kata_kunci')->nullable()->after('deskripsi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kategori_masalahs', function (Blueprint $table) {
            $table->dropColumn('kata_kunci');
        });

        Schema::table('master_kategori_masalahs', function (Blueprint $table) {
            $table->dropColumn('kata_kunci');
        });
    }
};
