<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kelas extends Model
{
    use SoftDeletes;

    protected $table = 'kelass';
    protected $fillable = [
        'tahun_ajaran_id',
        'tingkat',
        'jurusan_id',
        'rombel',
        'kurikulum',
        'wali_guru_id'
    ];
    protected $dates = ['deleted_at'];

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class);
    }
    public function waliGuru()
    {
        return $this->belongsTo(User::class, 'wali_guru_id');
    }
    public function jurusan()
    {
        return $this->belongsTo(Jurusan::class);
    }

    public function getLabelAttribute(): string
    {
        $parts = [$this->tingkat];
        if ($this->jurusan && $this->jurusan->nama)
            $parts[] = $this->jurusan->nama;
        $parts[] = (string) $this->rombel;
        return implode(' ', $parts);
    }

    public function siswas()
    {
        return $this->belongsToMany(Siswa::class, 'siswa_kelass', 'kelas_id', 'siswa_id')
            ->withPivot(['tahun_ajaran_id', 'is_active', 'joined_at', 'left_at'])
            ->withTimestamps();
    }
}
