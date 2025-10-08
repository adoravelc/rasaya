<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TahunAjaran extends Model
{
    use SoftDeletes;
    protected $fillable = ['nama', 'mulai', 'selesai', 'is_active'];

    public function kelas()
    {
        return $this->hasMany(Kelas::class);
    }

    // scope untuk yang aktif
    public function scopeAktif($q)
    {
        return $q->where('is_active', true);
    }
}
