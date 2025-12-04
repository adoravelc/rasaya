<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisis_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analisis_entry_id')->constrained('analisis_entries')->onDelete('cascade');
            
            // Original data dari ML
            $table->string('original_kategori');
            $table->text('original_rekomendasi');
            
            // Revised data dari guru
            $table->string('revised_kategori');
            $table->text('revised_rekomendasi');
            
            // Context untuk ML learning
            $table->text('original_text'); // Text yang dianalisis
            
            // Metadata
            $table->foreignId('revised_by')->constrained('users');
            $table->text('revision_notes')->nullable(); // Optional: catatan kenapa direvisi
            
            // ML feedback tracking
            $table->boolean('sent_to_ml')->default(false);
            $table->timestamp('sent_to_ml_at')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisis_revisions');
    }
};
