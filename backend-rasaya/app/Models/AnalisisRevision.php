<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisisRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'analisis_entry_id',
        'original_kategori',
        'original_rekomendasi',
        'revised_kategori',
        'revised_rekomendasi',
        'original_text',
        'revised_by',
        'revision_notes',
        'sent_to_ml',
        'sent_to_ml_at',
    ];

    protected $casts = [
        'sent_to_ml' => 'boolean',
        'sent_to_ml_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function analisisEntry(): BelongsTo
    {
        return $this->belongsTo(AnalisisEntry::class);
    }

    public function revisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revised_by');
    }
}
