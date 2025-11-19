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
        'is_friend',
        'tanggal',
        'teks',
        'gambar',
        'status_upload',
        'meta',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'status_upload' => 'integer',
        'is_friend' => 'boolean',
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

    // relasi kategori telah dihapus (pivot kategori_input_siswas di-drop)
}