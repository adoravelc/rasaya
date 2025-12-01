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
        Schema::table('slot_bookings', function (Blueprint $table) {
            // Menambahkan kolom untuk tracking siapa yang cancel booking
            // NULL = canceled oleh siswa sendiri, atau jika lama tidak ada data
            // NOT NULL = canceled oleh user tertentu (biasanya Guru BK)
            $table->unsignedBigInteger('canceled_by_user_id')->nullable()->after('cancel_reason');
            $table->foreign('canceled_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slot_bookings', function (Blueprint $table) {
            $table->dropForeign(['canceled_by_user_id']);
            $table->dropColumn('canceled_by_user_id');
        });
    }
};
