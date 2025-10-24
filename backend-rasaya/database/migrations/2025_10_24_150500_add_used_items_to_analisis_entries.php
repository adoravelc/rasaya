<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('analisis_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('analisis_entries', 'used_items')) {
                $table->json('used_items')->nullable()->after('kata_kunci');
            }
        });
    }

    public function down(): void
    {
        Schema::table('analisis_entries', function (Blueprint $table) {
            if (Schema::hasColumn('analisis_entries', 'used_items')) {
                $table->dropColumn('used_items');
            }
        });
    }
};
