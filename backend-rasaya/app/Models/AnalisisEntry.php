<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalisisEntry extends Model
{
    use HasFactory;

    /** Sumber data yang dianalisis (opsional enum di DB) */
    public const SRC_INPUT_SISWA = 'Input Siswa';
    public const SRC_INPUT_GURU = 'Input Guru';
    public const SRC_GABUNGAN = 'Gabungan';

    protected $table = 'analisis_entries';

    protected $fillable = [
        'siswa_kelas_id',
        'created_by',
        'skor_sentimen',          // rata-rata skor dari rentang data
        'avg_mood',
        'kata_kunci',             // JSON: [{term:"telat",count:5}, ...]
        'summary',                // JSON ringkasan global dari ML service
        'clusters',               // JSON cluster negatif
        'categories_overview',    // JSON ranking kategori hasil analisis
        'auto_summary',           // teks kesimpulan otomatis untuk tampilan guru
        'used_items',             // JSON snapshot of used sources: [{type:"ref_self|ref_friend|guru", id:123}]
        'source',                 // lihat konstanta SRC_*
        'source_id',              // jika per-item, id sumbernya
        'tanggal_awal_proses',
        'tanggal_akhir_proses',
        'needs_attention',
        'handling_status',        // null|'handled'|'resolved' - status penanganan masalah
    ];

    protected $casts = [
        'skor_sentimen' => 'float',
        'avg_mood' => 'float',
        'kata_kunci' => 'array',
        'summary' => 'array',
        'clusters' => 'array',
        'categories_overview' => 'array',
        'used_items' => 'array',
        'tanggal_awal_proses' => 'datetime',
        'tanggal_akhir_proses' => 'datetime',
        'needs_attention' => 'boolean',
    ];

    /* ===========================
     |  Relationships
     * =========================== */

    // siswa_kelas yang dianalisis
    public function siswaKelas()
    {
        return $this->belongsTo(SiswaKelas::class, 'siswa_kelas_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // rekomendasi hasil analisis
    public function rekomendasis()
    {
        return $this->hasMany(AnalisisRekomendasi::class, 'analisis_entry_id');
    }

    /* ===========================
     |  Scopes
     * =========================== */

    // filter berdasarkan siswa_kelas
    public function scopeOfSiswaKelas($q, int $siswaKelasId)
    {
        return $q->where('siswa_kelas_id', $siswaKelasId);
    }

    // filter berdasarkan rentang proses
    public function scopeBetween($q, string $fromDate, string $toDate)
    {
        return $q->whereDate('tanggal_awal_proses', '>=', $fromDate)
            ->whereDate('tanggal_akhir_proses', '<=', $toDate);
    }

    /* ===========================
     |  Accessors (helper)
     * =========================== */

    // label sentimen sederhana dari skor rata-rata
    public function getSentimentLabelAttribute(): string
    {
        $s = $this->skor_sentimen ?? 0.0;
        if ($s > 0.05)
            return 'positif';
        if ($s < -0.05)
            return 'negatif';
        return 'netral';
    }

    // ambil n kata kunci teratas (urut count desc)
    public function topKeywords(int $limit = 10): array
    {
        $items = collect($this->kata_kunci ?? []);
        return $items->sortByDesc(fn($x) => (int) ($x['count'] ?? 0))
            ->take($limit)
            ->values()
            ->all();
    }

    /* ===========================
     |  Mutators/utility
     * =========================== */

    // set kata_kunci dari map ["term" => count, ...]
    public function setKeywordsFromMap(array $map): void
    {
        $this->kata_kunci = collect($map)
            ->map(fn($count, $term) => ['term' => (string) $term, 'count' => (int) $count])
            ->values()
            ->all();
    }
}
