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
        Schema::create('counseling_referrals', function (Blueprint $table) {
            $table->id();
            // Siswa yang direferensikan (roster siswa_kelas)
            $table->foreignId('siswa_kelas_id')->constrained('siswa_kelass')->cascadeOnDelete();
            // Guru yang mengajukan (wali kelas atau guru lain)
            $table->foreignId('submitted_by_user_id')->constrained('users')->cascadeOnDelete();
            // Guru BK yang menerima (nullable sampai diterima)
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Status alur referral
            $table->enum('status', ['pending','accepted','rejected','scheduled'])->default('pending')->index();
            // Catatan tambahan dari pengaju atau guru BK
            $table->text('notes')->nullable();
            // Waktu diterima / ditolak
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            // Slot / booking yang dihasilkan (opsional)
            $table->foreignId('slot_konseling_id')->nullable()->constrained('slot_konselings')->nullOnDelete();
            $table->foreignId('slot_booking_id')->nullable()->constrained('slot_bookings')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counseling_referrals');
    }
};
