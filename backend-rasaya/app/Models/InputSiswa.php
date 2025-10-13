<?php

namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class InputSiswa extends Model
{
    use SoftDeletes;
    protected $fillable = ['siswa_id', 'tanggal', 'teks', 'avg_emosi', 'meta'];
    protected $casts = ['tanggal' => 'date', 'avg_emosi' => 'float', 'meta' => 'array'];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
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
