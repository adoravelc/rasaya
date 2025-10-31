<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class Siswa extends Model
{
    use SoftDeletes;
    protected $fillable = ['user_id'];
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'int';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function kelass()
    {
        return $this->belongsToMany(Kelas::class, 'siswa_kelass', 'siswa_id', 'kelas_id')
            ->withPivot(['tahun_ajaran_id', 'is_active', 'joined_at', 'left_at'])
            ->withTimestamps();
    }
}
