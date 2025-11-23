<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SlotBooking;

class SiswaPrivateSessionController extends Controller
{
    /**
     * Return upcoming private counseling session for authenticated student as JSON.
     */
    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'siswa') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        // Ambil active siswa_kelas id via latest aktif record
        $siswa = $user->siswa; // relation: user->siswa assumed
        if (!$siswa) {
            return response()->json(['session' => null]);
        }
        $activeSiswaKelasId = optional($siswa->kelass()->wherePivot('is_active', true)->orderByDesc('pivot_joined_at')->first())->pivot->siswa_id
            ? $siswa->kelass()->wherePivot('is_active', true)->orderByDesc('pivot_joined_at')->first()->pivot->siswa_id
            : null;

        // Query booking private mendatang
        $booking = SlotBooking::with(['slot','slot.guru.user'])
            ->where('siswa_kelas_id', $activeSiswaKelasId)
            ->whereHas('slot', fn($q) => $q->where('is_private', true)->where('start_at', '>', now()))
            ->orderBy('id')
            ->first();

        if (!$booking) {
            return response()->json(['session' => null]);
        }
        $slot = $booking->slot;
        return response()->json([
            'session' => [
                'slot_id' => $slot->id,
                'booking_id' => $booking->id,
                'start_at' => $slot->start_at?->toIso8601String(),
                'end_at' => $slot->end_at?->toIso8601String(),
                'lokasi' => $slot->lokasi,
                'notes' => $slot->notes,
                'guru_bk' => optional($slot->guru->user)->name,
            ],
        ]);
    }
}
