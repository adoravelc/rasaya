<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (!Schema::hasColumn('siswas', 'is_active')) {
            Schema::table('siswas', function (Blueprint $table) {
                $table->boolean('is_active')->default(true);
            });
        }
        if (!Schema::hasColumn('siswas', 'inactive_reason')) {
            Schema::table('siswas', function (Blueprint $table) {
                $table->string('inactive_reason', 255)->nullable();
            });
        }
        if (!Schema::hasColumn('siswas', 'inactive_at')) {
            Schema::table('siswas', function (Blueprint $table) {
                $table->timestamp('inactive_at')->nullable();
            });
        }
    }
    public function down() {
        if (Schema::hasColumn('siswas', 'inactive_at') || Schema::hasColumn('siswas', 'inactive_reason') || Schema::hasColumn('siswas', 'is_active')) {
            Schema::table('siswas', function (Blueprint $table) {
                if (Schema::hasColumn('siswas', 'inactive_at')) $table->dropColumn('inactive_at');
                if (Schema::hasColumn('siswas', 'inactive_reason')) $table->dropColumn('inactive_reason');
                if (Schema::hasColumn('siswas', 'is_active')) $table->dropColumn('is_active');
            });
        }
    }
};
