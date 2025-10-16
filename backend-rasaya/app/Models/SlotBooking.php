<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SlotBooking extends Model
{
    use SoftDeletes;

    protected $fillable = ['slot_id', 'siswa_kelas_id', 'status', 'held_until', 'cancel_reason'];
    protected $casts = ['held_until' => 'datetime'];

    public function slot()
    {
        return $this->belongsTo(SlotKonseling::class, 'slot_id');
    }
    public function siswaKelas()
    {
        return $this->belongsTo(SiswaKelas::class);
    }
}
