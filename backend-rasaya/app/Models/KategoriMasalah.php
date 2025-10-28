<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KategoriMasalah extends Model
{
    use SoftDeletes;
    protected $fillable = ['kode','nama','deskripsi','is_active'];
    public function scopeAktif($q){ return $q->where('is_active',true); }

    public function masters()
    {
        return $this->belongsToMany(\App\Models\MasterRekomendasi::class, 'kategori_masalah_master_rekomendasi', 'kategori_masalah_id', 'master_rekomendasi_id');
    }
}
