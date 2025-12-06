<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop table if exists to remove unused revision audit trail
        Schema::dropIfExists('analisis_revisions');
    }

    public function down(): void
    {
        // Recreate minimal table if rolled back (structure based on original)
        Schema::create('analisis_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('analisis_entry_id');
            $table->string('original_kategori')->nullable();
            $table->text('original_rekomendasi')->nullable();
            $table->string('revised_kategori')->nullable();
            $table->text('revised_rekomendasi')->nullable();
            $table->longText('original_text')->nullable();
            $table->unsignedBigInteger('revised_by')->nullable();
            $table->text('revision_notes')->nullable();
            $table->boolean('sent_to_ml')->default(false);
            $table->timestamp('sent_to_ml_at')->nullable();
            $table->timestamps();
        });
    }
};