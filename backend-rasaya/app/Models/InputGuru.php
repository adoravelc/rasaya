<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InputGuru extends Model
{
    use SoftDeletes;

    // Kalau kamu masih punya $fillable lama (isi, iduser_guru, dst) — ganti dengan ini
    protected $fillable = [
        'guru_id',        // FK -> gurus.user_id
        'siswa_kelas_id', // FK -> siswa_kelass.id
        'tanggal',        // date
        'teks',           // catatan/isi observasi
        'gambar',         // optional
        'kondisi_siswa',  // enum
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    // relasi
    public function siswaKelas()
    {
        return $this->belongsTo(SiswaKelas::class);
    }

    public function kategoris()
    {
        // pivot: kategori_input_gurus (input_guru_id, kategori_id)
        return $this->belongsToMany(KategoriMasalah::class, 'kategori_input_gurus', 'input_guru_id', 'kategori_id')
            ->withTimestamps();
    }
}
