<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PemantauanEmosiSiswa extends Model
{
    use SoftDeletes;

    protected $table = 'pemantauan_emosi_siswas';

    // GANTI field
    protected $fillable = [
        'siswa_kelas_id', 'tanggal', 'sesi', 'skor', 'gambar', 'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'skor'    => 'integer',
    ];

    public function siswaKelas()
    {
        return $this->belongsTo(SiswaKelas::class, 'siswa_kelas_id');
    }
}