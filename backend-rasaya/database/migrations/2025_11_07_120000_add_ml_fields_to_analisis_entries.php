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
            if (!Schema::hasColumn('analisis_entries', 'summary')) {
                $t->json('summary')->nullable()->after('kata_kunci');
            }
            if (!Schema::hasColumn('analisis_entries', 'clusters')) {
                $t->json('clusters')->nullable()->after('summary');
            }
            if (!Schema::hasColumn('analisis_entries', 'categories_overview')) {
                $t->json('categories_overview')->nullable()->after('clusters');
            }
            if (!Schema::hasColumn('analisis_entries', 'auto_summary')) {
                $t->text('auto_summary')->nullable()->after('categories_overview');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analisis_entries', function (Blueprint $t) {
            if (Schema::hasColumn('analisis_entries', 'auto_summary')) {
                $t->dropColumn('auto_summary');
            }
            if (Schema::hasColumn('analisis_entries', 'categories_overview')) {
                $t->dropColumn('categories_overview');
            }
            if (Schema::hasColumn('analisis_entries', 'clusters')) {
                $t->dropColumn('clusters');
            }
            if (Schema::hasColumn('analisis_entries', 'summary')) {
                $t->dropColumn('summary');
            }
        });
    }
};
