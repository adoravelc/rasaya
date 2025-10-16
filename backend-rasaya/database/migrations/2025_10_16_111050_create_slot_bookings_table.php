<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('slot_bookings', function (Blueprint $t) {
            $t->id();

            $t->foreignId('slot_id')->constrained('slot_konselings')->cascadeOnDelete();

            // konek roster siswa_kelas (PERMINTAANMU)
            $t->foreignId('siswa_kelas_id')->constrained('siswa_kelass')->cascadeOnDelete();

            $t->enum('status', ['held', 'booked', 'canceled', 'completed', 'no_show'])
                ->default('booked')->index();

            $t->dateTimeTz('held_until')->nullable(); // kalau nanti pakai hold
            $t->string('cancel_reason')->nullable();

            $t->timestamps();
            $t->softDeletes();

            // Satu siswa tidak boleh double-book di rentang waktu yang sama
            $t->unique(['slot_id', 'siswa_kelas_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slot_bookings');
    }
};
