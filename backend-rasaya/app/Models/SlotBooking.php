<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SlotBooking extends Model
{
    use SoftDeletes;

    protected $fillable = ['slot_id', 'siswa_kelas_id', 'status', 'cancel_reason', 'canceled_by_user_id'];
    protected $casts = [];

    public function slot()
    {
        return $this->belongsTo(SlotKonseling::class, 'slot_id');
    }
    public function siswaKelas()
    {
        return $this->belongsTo(SiswaKelas::class);
    }
    public function canceledBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'canceled_by_user_id');
    }
}
