<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Data Cleanup: Ubah status 'held' jadi 'booked' dulu agar tidak error saat ENUM diubah
        // Kita gunakan updateOrInsert atau update biasa
        DB::table('slot_bookings')
            ->where('status', 'held')
            ->update(['status' => 'booked']);

        // 2. Drop kolom (Pastikan library doctrine/dbal terinstall jika pakai Laravel versi lama)
        Schema::table('slot_bookings', function (Blueprint $table) {
            $table->dropColumn('held_until');
        });

        // 3. Alter ENUM menggunakan Raw SQL yang aman terhadap Prefix
        $prefix = DB::getTablePrefix();
        $table = $prefix . 'slot_bookings';
        
        // Gunakan backticks (`) agar aman jika ada karakter khusus pada nama tabel
        DB::statement("ALTER TABLE `$table` MODIFY COLUMN status ENUM('booked', 'canceled', 'completed', 'no_show') NOT NULL DEFAULT 'booked'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Kembalikan opsi ENUM 'held' terlebih dahulu
        $prefix = DB::getTablePrefix();
        $table = $prefix . 'slot_bookings';

        DB::statement("ALTER TABLE `$table` MODIFY COLUMN status ENUM('held', 'booked', 'canceled', 'completed', 'no_show') NOT NULL DEFAULT 'booked'");

        // 2. Tambahkan kembali kolom held_until
        Schema::table('slot_bookings', function (Blueprint $table) {
            $table->dateTimeTz('held_until')->nullable()->after('status');
        });
    }
};