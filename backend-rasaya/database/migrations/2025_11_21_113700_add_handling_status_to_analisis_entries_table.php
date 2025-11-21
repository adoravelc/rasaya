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
            $table->enum('handling_status', ['handled', 'resolved'])->nullable()->after('needs_attention')
                ->comment('Status penanganan: handled=sedang ditangani, resolved=selesai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analisis_entries', function (Blueprint $table) {
            $table->dropColumn('handling_status');
        });
    }
};
