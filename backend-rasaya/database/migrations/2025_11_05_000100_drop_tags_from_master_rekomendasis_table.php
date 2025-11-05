<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('master_rekomendasis') && Schema::hasColumn('master_rekomendasis', 'tags')) {
            Schema::table('master_rekomendasis', function (Blueprint $table) {
                $table->dropColumn('tags');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('master_rekomendasis') && !Schema::hasColumn('master_rekomendasis', 'tags')) {
            Schema::table('master_rekomendasis', function (Blueprint $table) {
                $table->json('tags')->nullable()->after('rules');
            });
        }
    }
};
