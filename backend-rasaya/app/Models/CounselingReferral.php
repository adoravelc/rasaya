<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CounselingReferral extends Model
{
    use HasFactory;

    protected $fillable = [
        'siswa_kelas_id',
        'submitted_by_user_id',
        'accepted_by_user_id',
        'status',
        'notes',
        'accepted_at',
        'rejected_at',
        'slot_konseling_id',
        'slot_booking_id',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    // Relationships
    public function siswaKelas()
    {
        return $this->belongsTo(SiswaKelas::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function acceptedBy()
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function slot()
    {
        return $this->belongsTo(SlotKonseling::class, 'slot_konseling_id');
    }

    public function booking()
    {
        return $this->belongsTo(SlotBooking::class, 'slot_booking_id');
    }

    // Scopes
    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeAccepted($q)
    {
        return $q->where('status', 'accepted');
    }

    public function scopeScheduled($q)
    {
        return $q->where('status', 'scheduled');
    }

    public function scopeForSiswaKelas($q, int $siswaKelasId)
    {
        return $q->where('siswa_kelas_id', $siswaKelasId);
    }

    // State transitions
    public function markAccepted(User $guruBkUser): void
    {
        $this->status = 'accepted';
        $this->accepted_by_user_id = $guruBkUser->id;
        $this->accepted_at = now();
        $this->save();
    }

    public function markRejected(User $guruBkUser, ?string $notes = null): void
    {
        $this->status = 'rejected';
        $this->accepted_by_user_id = $guruBkUser->id; // store as actor
        $this->rejected_at = now();
        if ($notes) {
            $this->notes = trim(($this->notes ? $this->notes."\n" : '') . '[REJECT] ' . $notes);
        }
        $this->save();
    }

    public function attachSchedule(SlotKonseling $slot, SlotBooking $booking): void
    {
        $this->slot_konseling_id = $slot->id;
        $this->slot_booking_id = $booking->id;
        $this->status = 'scheduled';
        $this->save();
    }
}
