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
        Schema::table('analisis_entries', function (Blueprint $t) {
            if (!Schema::hasColumn('analisis_entries', 'clusters')) {
                // Prefer placing after 'summary' if present to match older migration ordering
                if (Schema::hasColumn('analisis_entries', 'summary')) {
                    $t->json('clusters')->nullable()->after('summary');
                } else {
                    $t->json('clusters')->nullable();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analisis_entries', function (Blueprint $t) {
            if (Schema::hasColumn('analisis_entries', 'clusters')) {
                $t->dropColumn('clusters');
            }
        });
    }
};
