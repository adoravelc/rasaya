<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InputSiswa extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'siswa_kelas_id',
        'siswa_dilapor_kelas_id',
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

    // Pelapor (roster aktif)
    public function siswaKelas()
    {
        return $this->belongsTo(SiswaKelas::class, 'siswa_kelas_id');
    }

    // Yang dilaporkan (opsional)
    public function siswaDilaporKelas()
    {
        return $this->belongsTo(SiswaKelas::class, 'siswa_dilapor_kelas_id');
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