<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Create table if not exists (previous failed run may have created without FKs)
        if (!Schema::hasTable('master_kategori_masalah_kategori_masalah')) {
            Schema::create('master_kategori_masalah_kategori_masalah', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('master_kategori_masalah_id');
                $t->unsignedBigInteger('kategori_masalah_id');
                $t->timestamps();
            });
        } else {
            Schema::table('master_kategori_masalah_kategori_masalah', function (Blueprint $t) {
                if (!Schema::hasColumn('master_kategori_masalah_kategori_masalah', 'master_kategori_masalah_id')) {
                    $t->unsignedBigInteger('master_kategori_masalah_id');
                }
                if (!Schema::hasColumn('master_kategori_masalah_kategori_masalah', 'kategori_masalah_id')) {
                    $t->unsignedBigInteger('kategori_masalah_id');
                }
                if (!Schema::hasColumn('master_kategori_masalah_kategori_masalah', 'created_at')) {
                    $t->timestamps();
                }
            });
        }

        // Add indexes and foreign keys with short names to avoid MySQL 64-char limit
        Schema::table('master_kategori_masalah_kategori_masalah', function (Blueprint $t) {
            // indexes (safe if exist)
            $t->index('master_kategori_masalah_id', 'idx_mkmm_master');
            $t->index('kategori_masalah_id', 'idx_mkmm_kategori');
            // constraints with short names
            $t->foreign('master_kategori_masalah_id', 'fk_mkmm_master')
                ->references('id')->on('master_kategori_masalahs')->onDelete('cascade');
            $t->foreign('kategori_masalah_id', 'fk_mkmm_kategori')
                ->references('id')->on('kategori_masalahs')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('master_kategori_masalah_kategori_masalah')) {
            Schema::table('master_kategori_masalah_kategori_masalah', function (Blueprint $t) {
                // drop FKs if exist
                try { $t->dropForeign('fk_mkmm_master'); } catch (\Throwable $e) {}
                try { $t->dropForeign('fk_mkmm_kategori'); } catch (\Throwable $e) {}
                try { $t->dropIndex('idx_mkmm_master'); } catch (\Throwable $e) {}
                try { $t->dropIndex('idx_mkmm_kategori'); } catch (\Throwable $e) {}
            });
        }
        Schema::dropIfExists('master_kategori_masalah_kategori_masalah');
    }
};
