<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalisisRekomendasi extends Model
{
    protected $fillable = [
        'analisis_entry_id',
        'master_rekomendasi_id',
        'judul',
        'deskripsi',
        'severity',
        'match_score',
        'status',
        'rejected_kategori_id',
        'selected_master_rekomendasi_id',
        'decided_by',
        'decided_at',
        'kategori_masalah_id',
        'rules'
    ];

    protected $casts = [
        'match_score' => 'float',
        'decided_at' => 'datetime',
        'rules' => 'array',
    ];

    public function master()
    {
        return $this->belongsTo(MasterRekomendasi::class, 'master_rekomendasi_id');
    }
    public function selectedMaster()
    {
        return $this->belongsTo(MasterRekomendasi::class, 'selected_master_rekomendasi_id');
    }
    public function rejectedKategori()
    {
        return $this->belongsTo(\App\Models\KategoriMasalah::class, 'rejected_kategori_id');
    }
    public function analisis()
    {
        return $this->belongsTo(AnalisisEntry::class, 'analisis_entry_id');
    }

    public function kategoriMasalah()
    {
        return $this->belongsTo(\App\Models\KategoriMasalah::class, 'kategori_masalah_id');
    }
}
