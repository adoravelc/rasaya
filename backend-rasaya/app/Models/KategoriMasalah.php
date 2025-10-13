<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KategoriMasalah extends Model
{
    use SoftDeletes;
    protected $fillable = ['kode','nama','deskripsi','is_active'];
    public function scopeAktif($q){ return $q->where('is_active',true); }
}
