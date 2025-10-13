<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InputSiswa extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'siswa_id',
        'siswa_dilapor_id',
        'tanggal',
        'teks',
        'avg_emosi',
        'gambar',
        'status_upload',
        'meta',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'avg_emosi' => 'float',
        'status_upload' => 'integer',
        'meta' => 'array',
    ];

    // relasi ke Siswa yang mengisi (key = user_id)
    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'siswa_id', 'user_id');
    }

    // relasi ke Siswa yang DILAPORKAN (optional)
    public function siswaDilapor()
    {
        return $this->belongsTo(Siswa::class, 'siswa_dilapor_id', 'user_id');
    }

    public function kategoris()
    {
        return $this->belongsToMany(
            KategoriMasalah::class,
            'kategori_input_siswas',
            'input_id',
            'kategori_id'
        )->withTimestamps();
    }
}
