<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RekomendasiRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'kategori_masalah_id',
        'requested_by',
        'judul',
        'deskripsi',
        'severity',
        'rules',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'rules' => 'array',
    ];

    public function kategori()
    {
        return $this->belongsTo(KategoriMasalah::class, 'kategori_masalah_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
