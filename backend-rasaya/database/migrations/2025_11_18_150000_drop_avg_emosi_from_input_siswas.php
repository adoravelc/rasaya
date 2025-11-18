<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('input_siswas', 'avg_emosi')) {
            Schema::table('input_siswas', function (Blueprint $t) {
                $t->dropColumn('avg_emosi');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('input_siswas', 'avg_emosi')) {
            Schema::table('input_siswas', function (Blueprint $t) {
                $t->decimal('avg_emosi', 3, 1)->nullable()->after('teks');
            });
        }
    }
};
