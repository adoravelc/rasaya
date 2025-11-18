<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('year_copy_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('source_year_id')->constrained('tahun_ajarans');
            $t->foreignId('target_year_id')->constrained('tahun_ajarans');
            $t->json('options')->nullable();
            $t->json('resolution')->nullable();
            $t->string('status')->default('queued'); // queued|running|succeeded|failed
            $t->unsignedTinyInteger('progress')->default(0);
            $t->json('log')->nullable();
            $t->text('error')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('year_copy_runs');
    }
};
