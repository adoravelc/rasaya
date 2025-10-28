<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterRekomendasi extends Model
{
    protected $fillable = ['kode', 'judul', 'deskripsi', 'severity', 'is_active', 'rules', 'tags'];
    protected $casts = [
        'is_active' => 'boolean',
        'rules' => 'array',
        'tags' => 'array',
    ];

    public function kategoris()
    {
        return $this->belongsToMany(\App\Models\KategoriMasalah::class, 'kategori_masalah_master_rekomendasi', 'master_rekomendasi_id', 'kategori_masalah_id');
    }
}

