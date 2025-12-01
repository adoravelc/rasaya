<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (!Schema::hasColumn('siswa_kelass', 'is_active')) {
            Schema::table('siswa_kelass', function (Blueprint $table) {
                $table->boolean('is_active')->default(true);
            });
        }
    }
    public function down() {
        if (Schema::hasColumn('siswa_kelass', 'is_active')) {
            Schema::table('siswa_kelass', function (Blueprint $table) {
                $table->dropColumn(['is_active']);
            });
        }
    }
};
