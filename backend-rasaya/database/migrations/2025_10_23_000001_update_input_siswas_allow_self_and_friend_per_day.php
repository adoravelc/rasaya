<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('input_siswas', function (Blueprint $t) {
            if (!Schema::hasColumn('input_siswas', 'is_friend')) {
                $t->boolean('is_friend')->default(false)->after('siswa_dilapor_kelas_id')->index();
            }
        });

        // Backfill is_friend based on siswa_dilapor_kelas_id
        DB::statement("UPDATE input_siswas SET is_friend = CASE WHEN siswa_dilapor_kelas_id IS NULL THEN 0 ELSE 1 END");

        // Ensure there's a standalone index on siswa_kelas_id so the FK isn't relying on the composite unique
        try {
            Schema::table('input_siswas', function (Blueprint $t) {
                $t->index('siswa_kelas_id', 'input_siswas_siswa_kelas_id_index');
            });
        } catch (Throwable $e) {
            // ignore if exists
        }

        // Drop old unique constraint on (siswa_kelas_id, tanggal)
        Schema::disableForeignKeyConstraints();
        try {
            Schema::table('input_siswas', function (Blueprint $t) {
                $t->dropUnique(['siswa_kelas_id', 'tanggal']);
            });
        } catch (Throwable $e) {
            // Fallback: attempt drop by guessed name
            try {
                DB::statement('ALTER TABLE `input_siswas` DROP INDEX `input_siswas_siswa_kelas_id_tanggal_unique`');
            } catch (Throwable $e2) {
                // ignore if already dropped
            }
        }
        Schema::enableForeignKeyConstraints();

        // Add new unique per type per day
        Schema::table('input_siswas', function (Blueprint $t) {
            $t->unique(['siswa_kelas_id', 'tanggal', 'is_friend'], 'input_siswas_per_type_per_day_unique');
        });
    }

    public function down(): void
    {
        // Revert unique and column
        try {
            Schema::table('input_siswas', function (Blueprint $t) {
                $t->dropUnique('input_siswas_per_type_per_day_unique');
            });
        } catch (Throwable $e) {
            // ignore
        }
        Schema::table('input_siswas', function (Blueprint $t) {
            if (Schema::hasColumn('input_siswas', 'is_friend')) {
                $t->dropColumn('is_friend');
            }
        });
        Schema::table('input_siswas', function (Blueprint $t) {
            $t->unique(['siswa_kelas_id', 'tanggal']);
        });
    }
};
