<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Guru extends Model
{
    protected $fillable = ['user_id','jenis'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
