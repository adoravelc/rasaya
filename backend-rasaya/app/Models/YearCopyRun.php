<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YearCopyRun extends Model
{
    protected $fillable = [
        'source_year_id','target_year_id','options','resolution',
        'status','progress','log','error','created_by'
    ];

    protected $casts = [
        'options' => 'array',
        'resolution' => 'array',
        'log' => 'array',
    ];
}
