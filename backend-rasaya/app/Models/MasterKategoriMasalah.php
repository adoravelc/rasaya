<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterKategoriMasalah extends Model
{
    use SoftDeletes;

    protected $table = 'master_kategori_masalahs';

    protected $fillable = [
        'kode',
        'nama',
        'deskripsi',
        'kata_kunci',
        'is_active',
    ];

    protected $casts = [
        'kata_kunci' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeAktif($q){ return $q->where('is_active', true); }

    public function subkategoris()
    {
        return $this->belongsToMany(
            KategoriMasalah::class,
            'master_kategori_masalah_kategori_masalah',
            'master_kategori_masalah_id',
            'kategori_masalah_id'
        )->withTimestamps();
    }

    public function inputGurus()
    {
        return $this->hasMany(InputGuru::class, 'master_kategori_masalah_id');
    }
}
