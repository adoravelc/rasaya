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
        Schema::create('slot_konselings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('guru_id')
                ->constrained('gurus', 'user_id')->cascadeOnDelete();

            $t->date('tanggal')->index();
            $t->dateTimeTz('start_at');
            $t->dateTimeTz('end_at');
            $t->unsignedSmallInteger('durasi_menit');
            $t->unsignedTinyInteger('capacity')->default(1);  // biasanya 1
            $t->unsignedTinyInteger('booked_count')->default(0);

            $t->enum('status', ['draft', 'published', 'archived', 'canceled'])
                ->default('published')->index();

            $t->string('lokasi')->nullable();    // opsional (ruang BK / meet link)
            $t->string('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();

            // Unik per guru per waktu mulai (hindari duplikat)
            $t->unique(['guru_id', 'start_at']);
            $t->index(['guru_id', 'tanggal']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slot_konselings');
    }
};
