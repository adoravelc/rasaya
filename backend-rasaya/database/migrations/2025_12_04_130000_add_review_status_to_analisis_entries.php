<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analisis_entries', function (Blueprint $table) {
            // Status review: pending_review, accepted, revised
            $table->enum('review_status', ['pending_review', 'accepted', 'revised'])
                  ->default('pending_review')
                  ->after('source');
            
            // Guru yang mereview (bisa null kalau otomatis accepted)
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->after('review_status');
            
            // Timestamp review
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('analisis_entries', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['review_status', 'reviewed_by', 'reviewed_at']);
        });
    }
};
