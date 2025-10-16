<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SlotKonseling;
use App\Models\SlotBooking;
use App\Models\SiswaKelas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Carbon\CarbonTimeZone;

class BookingKonselingController extends Controller
{
    /**
     * Helper: ambil siswa_kelas_id aktif milik user siswa.
     * - Asumsi relasi: User -> siswa (user_id) -> siswa_kelass (is_active=true).
     */
    private function getActiveRosterId(Request $r): int
    {
        $user = $r->user();
        $roster = SiswaKelas::query()
            ->where('siswa_id', optional($user->siswa)->user_id)   // kolom siswas.user_id
            ->where('is_active', true)
            ->latest('id')
            ->first();

        abort_unless($roster, 422, 'Data kelas aktif siswa tidak ditemukan.');
        return (int) $roster->id;
    }

    /**
     * GET /api/slots/available
     * Query params: from=YYYY-MM-DD, to=YYYY-MM-DD, guru_id (opsional)
     * Mengembalikan slot published yang belum penuh.
     */
    public function available(Request $r)
    {
        // Default range: hari ini s/d +30 hari
        $from = $r->date('from') ?? now()->startOfDay();
        $to = $r->date('to') ?? now()->addDays(30)->endOfDay();
        abort_if($from > $to, 422, 'Rentang tanggal tidak valid.');

        $q = SlotKonseling::query()
            ->with(['guru:user_id,nama']) // sesuaikan kolom model Guru
            ->where('status', 'published')
            ->whereBetween('start_at', [$from, $to])
            ->whereColumn('booked_count', '<', 1);

        if ($r->filled('guru_id')) {
            $q->where('guru_id', (int) $r->guru_id);
        }

        // Pagination ringan
        $rows = $q->orderBy('start_at')->paginate($r->integer('per_page', 20));

        // Format output: jam lokal WITA (opsional)
        $tz = new CarbonTimeZone('Asia/Makassar');
        $data = $rows->through(function (SlotKonseling $s) {
            return [
                'id' => $s->id,
                'guru' => ['id' => $s->guru_id, 'nama' => $s->guru->nama ?? '-'],
                'tanggal' => $s->tanggal->toDateString(),
                'start_at' => $s->start_at->toIso8601String(),
                'end_at' => $s->end_at->toIso8601String(),
                'durasi_menit' => $s->durasi_menit,
                'booked_count' => $s->booked_count,
                'lokasi' => $s->lokasi,
                'notes' => $s->notes,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    /**
     * POST /api/bookings
     * Body: { "slot_id": 123 }
     * Membuat booking secara atomik (anti-race).
     */
    public function book(Request $r)
    {
        $data = $r->validate([
            'slot_id' => ['required', 'integer', Rule::exists('slot_konselings', 'id')],
        ]);

        $slotId = (int) $data['slot_id'];
        $rosterId = $this->getActiveRosterId($r);

        // Cegah double-book overlap untuk siswa pada waktu yang sama (opsional kuat)
        // Bisa ditingkatkan: cek overlap terhadap slot->start_at/end_at.
        $already = SlotBooking::where('siswa_kelas_id', $rosterId)
            ->whereHas('slot', function ($q) use ($slotId) {
                $slot = SlotKonseling::find($slotId);
                if ($slot) {
                    $q->whereBetween('start_at', [$slot->start_at->clone()->subMinutes(1), $slot->end_at->clone()->addMinutes(1)])
                        ->where('status', 'published');
                }
            })
            ->whereIn('status', ['booked', 'held'])
            ->exists();
        abort_if($already, 422, 'Anda sudah memiliki booking pada waktu yang berdekatan.');

        // Transaksi & lock agar aman dari balapan
        $booking = DB::transaction(function () use ($slotId, $rosterId) {
            /** @var SlotKonseling $slot */
            $slot = SlotKonseling::lockForUpdate()->findOrFail($slotId);

            // Validasi ketersediaan
            abort_if($slot->status !== 'published', 422, 'Slot tidak tersedia.');
            abort_if($slot->start_at->isPast(), 422, 'Slot sudah berlalu.');
            abort_if($slot->booked_count >= 1, 422, 'Slot sudah penuh.');

            // Buat booking (unique: slot_id + siswa_kelas_id)
            $booking = SlotBooking::create([
                'slot_id' => $slot->id,
                'siswa_kelas_id' => $rosterId,
                'status' => 'booked',
            ]);

            // Update counter
            $slot->increment('booked_count');

            return $booking->load(['slot.guru', 'siswaKelas.siswa']);
        });

        return response()->json($booking, 201);
    }

    /**
     * GET /api/bookings/me
     * Daftar booking milik siswa (upcoming duluan).
     */
    public function myBookings(Request $r)
    {
        $rosterId = $this->getActiveRosterId($r);

        $rows = SlotBooking::with(['slot.guru'])
            ->where('siswa_kelas_id', $rosterId)
            ->orderByDesc('created_at')
            ->paginate($r->integer('per_page', 20));

        return response()->json($rows);
    }

    /**
     * POST /api/bookings/{id}/cancel
     * Batalkan booking milik siswa.
     */
    public function cancelMine(Request $r, int $id)
    {
        $rosterId = $this->getActiveRosterId($r);

        /** @var SlotBooking $booking */
        $booking = SlotBooking::with('slot')->where('id', $id)
            ->where('siswa_kelas_id', $rosterId)->firstOrFail();

        abort_if(!in_array($booking->status, ['booked', 'held']), 422, 'Booking tidak bisa dibatalkan.');
        abort_if($booking->slot->start_at->isPast(), 422, 'Slot sudah berlalu.');

        DB::transaction(function () use ($booking, $r) {
            $booking->update([
                'status' => 'canceled',
                'cancel_reason' => $r->string('reason') ?: null,
            ]);

            // kembalikan kuota jika sebelumnya booked
            if ($booking->wasChanged('status')) {
                $slot = SlotKonseling::lockForUpdate()->find($booking->slot_id);
                if ($slot && $slot->booked_count > 0) {
                    $slot->decrement('booked_count');
                }
            }
        });

        return response()->json(['ok' => true]);
    }
}
