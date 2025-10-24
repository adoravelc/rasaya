<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // No-op duplicate migration retained to preserve history; table created in 125056.
        if (Schema::hasTable('master_rekomendasis')) return;
        Schema::create('master_rekomendasis', function (Blueprint $t) {
            $t->id();
            $t->string('kode')->unique();
            $t->string('judul');
            $t->text('deskripsi')->nullable();
            $t->enum('severity', ['low', 'medium', 'high'])->default('low');
            $t->boolean('is_active')->default(true);
            $t->json('rules')->nullable();
            $t->json('tags')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('master_rekomendasis');
    }
};

