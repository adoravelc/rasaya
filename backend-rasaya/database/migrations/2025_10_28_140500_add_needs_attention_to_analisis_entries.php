<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('analisis_entries', function (Blueprint $t) {
            $t->boolean('needs_attention')->default(false)->after('tanggal_akhir_proses');
        });
    }

    public function down(): void
    {
        Schema::table('analisis_entries', function (Blueprint $t) {
            $t->dropColumn('needs_attention');
        });
    }
};
