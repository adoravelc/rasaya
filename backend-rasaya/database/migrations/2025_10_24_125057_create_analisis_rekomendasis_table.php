<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('analisis_rekomendasis', function (Blueprint $t) {
            $t->id();
            $t->foreignId('analisis_entry_id')
                ->constrained('analisis_entries')->cascadeOnDelete();

            $t->foreignId('master_rekomendasi_id')->nullable()
                ->constrained('master_rekomendasis')->nullOnDelete();

            $t->string('judul');               // snapshot judul master
            $t->text('deskripsi')->nullable(); // snapshot deskripsi master
            $t->enum('severity', ['low', 'medium', 'high'])->default('low');

            // skor kecocokan (0..1) atau skala bebas
            $t->decimal('match_score', 5, 3)->nullable();

            // status keputusan guru
            $t->enum('status', ['suggested', 'accepted', 'rejected'])->default('suggested');
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();

            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('analisis_rekomendasis');
    }
};
