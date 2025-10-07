<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class UserLoginHistory extends Model
{
    protected $fillable = ['user_id','ip_address','user_agent','logged_in_at','logged_out_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
