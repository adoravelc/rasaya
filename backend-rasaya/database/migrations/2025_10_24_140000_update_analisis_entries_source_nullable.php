<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Extend enum and allow NULL for aggregated entries; make source_id nullable
        DB::statement("ALTER TABLE `analisis_entries` MODIFY `source` ENUM('input_siswa','input_guru','gabungan') NULL");
        DB::statement("ALTER TABLE `analisis_entries` MODIFY `source_id` BIGINT UNSIGNED NULL");
    }

    public function down(): void
    {
        // Revert: disallow NULL and drop 'gabungan' from enum (set default to 'input_siswa')
        DB::statement("UPDATE `analisis_entries` SET `source` = 'input_siswa' WHERE `source` IS NULL");
        DB::statement("ALTER TABLE `analisis_entries` MODIFY `source` ENUM('input_siswa','input_guru') NOT NULL");
        DB::statement("ALTER TABLE `analisis_entries` MODIFY `source_id` BIGINT UNSIGNED NOT NULL");
    }
};
