<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('master_rekomendasis', function (Blueprint $t) {
            $t->id();
            $t->string('kode')->unique();             // ex: ATT-LATE
            $t->string('judul');                      // ex: Konseling ketertiban hadir
            $t->text('deskripsi')->nullable();        // ex: Ajakan pertemuan dgn orangtua, dsb
            $t->enum('severity', ['low', 'medium', 'high'])->default('low');
            $t->boolean('is_active')->default(true);

            // tag/aturan sederhana utk auto-suggest (berbasis kata kunci & sentimen)
            // contoh: { "any_keywords": ["telat","bolos"], "min_neg_score": -0.2 }
            $t->json('rules')->nullable();
            $t->json('tags')->nullable();             // opsional: ["disiplin","motivasi"]

            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('master_rekomendasis');
    }
};