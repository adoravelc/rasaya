<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guru extends Model
{
    use SoftDeletes;
    protected $fillable = ['user_id', 'jenis'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id'); // sesuaikan kalau pakai 'iduser_guru'
    }
    public function kelasDiwalikan()
    {
        return $this->hasMany(Kelas::class, 'wali_guru_id');
    }
}
