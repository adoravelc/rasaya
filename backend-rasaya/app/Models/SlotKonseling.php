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
        'notes',
        'is_private',
        'target_siswa_kelas_id'
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

    /**
     * Scope untuk slot yang tersedia (published dan belum di-book)
     */
    public function scopeAvailable($q)
    {
        return $q->where('status', 'published')
            ->where(function($qq){
                $qq->whereNull('booked_count')->orWhere('booked_count', 0);
            })
            ->where(function($qq){
                // Exclude private slots from generic availability listing
                $qq->whereNull('is_private')->orWhere('is_private', false);
            });
    }

    /**
     * Scope untuk slot yang sudah di-book
     */
    public function scopeBooked($q)
    {
        return $q->where('booked_count', '>', 0);
    }
}
