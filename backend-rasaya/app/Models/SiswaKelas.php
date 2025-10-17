<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiswaKelas extends Model
{
    protected $table = 'siswa_kelass';
    protected $fillable = [
        'tahun_ajaran_id','kelas_id','siswa_id','is_active','joined_at','left_at'
    ];

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    // siswas primary key is user_id; siswa_kelass.siswa_id also stores user_id
    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'siswa_id', 'user_id');
    }

    // helper label untuk dropdown
    public function getLabelAttribute(): string
    {
        $ta   = optional($this->tahunAjaran)->nama ?? optional($this->tahunAjaran)->tahun ?? '';
        $kls  = optional($this->kelas)->label ?? optional($this->kelas)->nama ?? '-';
        $nama = optional($this->siswa)->nama ?? optional($this->siswa->user)->name ?? '-';
        return trim("{$kls} • {$nama} ".($ta ? "({$ta})" : ''));
    }
}
