<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterRekomendasi extends Model
{
    protected $fillable = ['kode', 'judul', 'deskripsi', 'severity', 'is_active', 'rules', 'tags'];
    protected $casts = [
        'is_active' => 'boolean',
        'rules' => 'array',
        'tags' => 'array',
    ];
}

