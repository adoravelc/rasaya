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
        'master_kategori_masalah_id', // top-level topic chosen by guru (nullable)
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
        // relasi sub-kategori dihapus: pivot kategori_input_gurus telah di-drop
        return $this->belongsToMany(KategoriMasalah::class, 'kategori_input_gurus', 'input_guru_id', 'kategori_id')
            ->whereRaw('1=0'); // legacy no-op to avoid runtime errors if accidentally called
    }

    public function masterKategori()
    {
        return $this->belongsTo(MasterKategoriMasalah::class, 'master_kategori_masalah_id');
    }
}
