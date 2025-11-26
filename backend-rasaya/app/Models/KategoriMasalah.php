<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KategoriMasalah extends Model
{
    use SoftDeletes;
    protected $fillable = ['kode','nama','deskripsi','kata_kunci','is_active'];
    
    protected $casts = [
        'kata_kunci' => 'array',
        'is_active' => 'boolean',
    ];
    
    public function scopeAktif($q){ return $q->where('is_active',true); }

    public function masters()
    {
        return $this->belongsToMany(\App\Models\MasterRekomendasi::class, 'kategori_masalah_master_rekomendasi', 'kategori_masalah_id', 'master_rekomendasi_id');
    }

    public function topikBesars()
    {
        return $this->belongsToMany(
            \App\Models\MasterKategoriMasalah::class,
            'master_kategori_masalah_kategori_masalah',
            'kategori_masalah_id',
            'master_kategori_masalah_id'
        )->withTimestamps();
    }
}
