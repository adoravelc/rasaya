<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PemantauanEmosiSiswa extends Model
{
    use SoftDeletes;

    protected $table = 'pemantauan_emosi_siswas';

    protected $fillable = [
        'siswa_id', 'tanggal', 'sesi', 'skor', 'gambar',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'skor'    => 'integer',
    ];
}
