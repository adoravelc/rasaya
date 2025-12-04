<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cek dulu tabel & column exist atau tidak
        if (Schema::hasTable('analisis_entries') && Schema::hasColumn('analisis_entries', 'source')) {
            Schema::table('analisis_entries', function (Blueprint $table) {
                $table->string('source')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('analisis_entries') && Schema::hasColumn('analisis_entries', 'source')) {
            Schema::table('analisis_entries', function (Blueprint $table) {
                $table->string('source')->nullable(false)->change();
            });
        }
    }
};
