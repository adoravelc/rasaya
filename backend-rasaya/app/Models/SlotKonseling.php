<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SlotKonseling extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'guru_id',
        'tanggal',
        'start_at',
        'end_at',
        'durasi_menit',
        'booked_count',
        'status',
        'lokasi',
        'notes'
    ];
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'tanggal' => 'date',
    ];

    public function guru()
    {
        return $this->belongsTo(Guru::class, 'guru_id', 'user_id');
    }
    public function bookings()
    {
        return $this->hasMany(SlotBooking::class, 'slot_id');
    }

    public function scopeAvailable($q)
    {
        return $q->where('status', 'available')
            ->where(function($qq){
                $qq->whereNull('booked_count')->orWhere('booked_count', 0);
            });
    }
}
