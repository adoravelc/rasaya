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
        Schema::table('analisis_entries', function (Blueprint $table) {
            $table->foreignId('revised_kategori_id')->nullable()->after('needs_attention')->constrained('kategori_masalahs')->nullOnDelete();
            $table->text('revision_reason')->nullable()->after('revised_kategori_id');
            $table->foreignId('revised_by')->nullable()->after('revision_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('revised_at')->nullable()->after('revised_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analisis_entries', function (Blueprint $table) {
            $table->dropForeign(['revised_kategori_id']);
            $table->dropForeign(['revised_by']);
            $table->dropColumn(['revised_kategori_id', 'revision_reason', 'revised_by', 'revised_at']);
        });
    }
};
