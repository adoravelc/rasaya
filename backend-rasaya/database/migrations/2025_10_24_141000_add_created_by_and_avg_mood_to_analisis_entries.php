<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('analisis_entries', function (Blueprint $t) {
            if (!Schema::hasColumn('analisis_entries', 'created_by')) {
                $t->foreignId('created_by')->nullable()->after('id')
                  ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('analisis_entries', 'avg_mood')) {
                $t->decimal('avg_mood', 5, 2)->nullable()->after('skor_sentimen');
            }
        });
    }

    public function down(): void
    {
        Schema::table('analisis_entries', function (Blueprint $t) {
            if (Schema::hasColumn('analisis_entries', 'created_by')) {
                $t->dropConstrainedForeignId('created_by');
            }
            if (Schema::hasColumn('analisis_entries', 'avg_mood')) {
                $t->dropColumn('avg_mood');
            }
        });
    }
};
