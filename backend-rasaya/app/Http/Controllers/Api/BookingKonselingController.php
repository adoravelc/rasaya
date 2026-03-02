<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SlotKonseling;
use App\Models\SlotBooking;
use App\Models\SiswaKelas;
use App\Services\GuestSandboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class BookingKonselingController extends Controller
{
    private function sandbox(): GuestSandboxService
    {
        return app(GuestSandboxService::class);
    }

    /**
     * Helper: ambil siswa_kelas_id aktif milik user siswa.
     * - Asumsi relasi: User -> siswa (user_id) -> siswa_kelass (is_active=true).
     */
    private function getActiveRosterId(Request $r): int
    {
        $user = $r->user();
        $roster = SiswaKelas::query()
            // siswa_kelass.siswa_id menyimpan siswas.user_id
            ->where('siswa_id', optional($user->siswa)->user_id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        abort_unless((bool) $roster, 422, 'Data kelas aktif siswa tidak ditemukan.');
        return (int) ($roster->id ?? 0);
    }

    /**
     * GET /api/slots/available
     * Query params: from=YYYY-MM-DD, to=YYYY-MM-DD, guru_id (opsional)
     * Mengembalikan slot published yang belum penuh dan belum lewat (WITA).
     */
    public function available(Request $r)
    {
        // Default range: hari ini s/d +30 hari
        $from = ($r->date('from')?->startOfDay()) ?? now()->startOfDay();
        // Pastikan batas akhir mencakup seluruh hari ketika parameter 'to' diberikan
        $to = ($r->date('to')?->endOfDay()) ?? now()->addDays(30)->endOfDay();
        abort_if($from > $to, 422, 'Rentang tanggal tidak valid.');

        // Waktu sekarang dalam WITA (UTC+8)
        $nowWita = now()->setTimezone('Asia/Makassar');

        $q = SlotKonseling::query()
            // Eager-load user for guru to obtain display name
            ->with(['guru.user'])
            ->where('status', 'published')
            ->whereBetween('start_at', [$from, $to])
            ->where('booked_count', '<', 1)
            // Filter: hanya slot yang end_at belum lewat (dalam WITA)
                ->where('start_at', '>', $nowWita);

        if ($r->filled('guru_id')) {
            $q->where('guru_id', (int) $r->guru_id);
        }

        // Pagination ringan
        $rows = $q->orderBy('start_at')->paginate($r->integer('per_page', 20));

        // Transform items but keep paginator metadata
        $mapped = $rows->getCollection()->map(function (SlotKonseling $s) {
            return [
                'id' => $s->id,
                'guru' => [
                    'id' => $s->guru_id,
                    'nama' => optional(optional($s->guru)->user)->name ?? '-',
                ],
                'tanggal' => optional($s->start_at)->toDateString(),
                'start_at' => $s->start_at->toIso8601String(),
                'end_at' => $s->end_at->toIso8601String(),
                'durasi_menit' => $s->durasi_menit,
                'booked_count' => $s->booked_count,
                'lokasi' => $s->lokasi,
                'notes' => $s->notes,
            ];
        })->values();

        return response()->json([
            'data' => $mapped,
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

        // Cek apakah siswa pernah cancel booking di slot yang sama
        $previouslyCanceled = SlotBooking::where('siswa_kelas_id', $rosterId)
            ->where('slot_id', $slotId)
            ->where('status', 'canceled')
            ->exists();
        
        if ($previouslyCanceled) {
            return response()->json([
                'message' => 'Kamu sudah melakukan cancel untuk slot ini, mohon memilih slot pada waktu yang lain.'
            ], 422);
        }

        if ($this->sandbox()->isGuestSiswa($r)) {
            $slot = SlotKonseling::with(['guru.user'])->findOrFail($slotId);
            abort_if($slot->status !== 'published', 422, 'Slot tidak tersedia.');
            abort_if($slot->start_at->isPast(), 422, 'Slot sudah berlalu.');

            $items = $this->sandbox()->getBooking($r);
            $already = collect($items)->contains(fn(array $x) =>
                (int) ($x['slot_id'] ?? 0) === $slotId
                && (string) ($x['status'] ?? 'booked') === 'booked'
            );
            if ($already) {
                return response()->json(['message' => 'Slot sudah kamu booking.'], 422);
            }

            $booking = [
                'id' => $this->sandbox()->nextBookingId($r),
                'slot_id' => $slotId,
                'siswa_kelas_id' => $rosterId,
                'status' => 'booked',
                'cancel_reason' => null,
                'canceled_by_user_id' => null,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
                'slot' => [
                    'id' => $slot->id,
                    'guru_id' => $slot->guru_id,
                    'status' => $slot->status,
                    'start_at' => $slot->start_at?->toIso8601String(),
                    'end_at' => $slot->end_at?->toIso8601String(),
                    'booked_count' => 1,
                    'lokasi' => $slot->lokasi,
                    'notes' => $slot->notes,
                    'guru' => [
                        'id' => $slot->guru_id,
                        'user' => [
                            'name' => optional(optional($slot->guru)->user)->name ?? '-',
                        ],
                    ],
                ],
            ];

            $items[] = $booking;
            $this->sandbox()->putBooking($r, $items);

            return response()->json($booking, 201);
        }

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
            ->where('status', 'booked')
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
            $slot->booked_count = $slot->booked_count + 1;
            $slot->save();

            // Send notification to Guru BK
            $siswaName = $booking->siswaKelas->siswa->user->name ?? 'Siswa';
            $slotTime = $slot->start_at->format('d M Y H:i');
            
            \App\Helpers\NotificationHelper::notifyGuruBkKonselingRequest(
                $slot->guru_id,
                $booking->id,
                $siswaName,
                $slotTime
            );

            return $booking->load(['slot.guru', 'siswaKelas.siswa']);
        });

        return response()->json($booking, 201);
    }

    /**
     * GET /api/bookings/me
     * Daftar booking milik siswa (upcoming duluan).
     * Include canceled bookings untuk ditampilkan di app.
     */
    public function myBookings(Request $r)
    {
        if ($this->sandbox()->isGuestSiswa($r)) {
            $rosterId = $this->getActiveRosterId($r);
            $rows = collect($this->sandbox()->getBooking($r))
                ->filter(fn(array $x) => (int) ($x['siswa_kelas_id'] ?? 0) === $rosterId)
                ->sortByDesc('created_at')
                ->values()
                ->all();

            return response()->json([
                'current_page' => 1,
                'data' => $rows,
                'from' => count($rows) > 0 ? 1 : null,
                'last_page' => 1,
                'per_page' => count($rows),
                'to' => count($rows),
                'total' => count($rows),
            ]);
        }

        $rosterId = $this->getActiveRosterId($r);

        $rows = SlotBooking::with(['slot.guru.user', 'siswaKelas.siswa.user', 'canceledBy'])
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
        if ($this->sandbox()->isGuestSiswa($r)) {
            $rosterId = $this->getActiveRosterId($r);
            $items = $this->sandbox()->getBooking($r);
            $index = collect($items)->search(fn(array $x) =>
                (int) ($x['id'] ?? 0) === $id
                && (int) ($x['siswa_kelas_id'] ?? 0) === $rosterId
            );

            abort_if($index === false, 404);
            if (($items[$index]['status'] ?? '') !== 'booked') {
                return response()->json(['message' => 'Booking tidak bisa dibatalkan.'], 422);
            }

            $items[$index]['status'] = 'canceled';
            $items[$index]['cancel_reason'] = (string) ($r->input('reason') ?: 'Tidak ada alasan');
            $items[$index]['canceled_by_user_id'] = $r->user()->id;
            $items[$index]['updated_at'] = now()->toISOString();
            $this->sandbox()->putBooking($r, $items);

            return response()->json(['ok' => true]);
        }

        $rosterId = $this->getActiveRosterId($r);

        /** @var SlotBooking $booking */
        $booking = SlotBooking::with('slot')->where('id', $id)
            ->where('siswa_kelas_id', $rosterId)->firstOrFail();

        abort_if($booking->status !== 'booked', 422, 'Booking tidak bisa dibatalkan.');
        abort_if($booking->slot->start_at->isPast(), 422, 'Slot sudah berlalu.');

        DB::transaction(function () use ($booking, $r) {
            $cancelReason = $r->string('reason') ?: 'Tidak ada alasan';
            
            $booking->update([
                'status' => 'canceled',
                'cancel_reason' => $cancelReason,
                'canceled_by_user_id' => $r->user()->id, // Track siapa yang cancel
            ]);

            // kembalikan kuota jika sebelumnya booked
            if ($booking->wasChanged('status')) {
                $slot = SlotKonseling::lockForUpdate()->find($booking->slot_id);
                if ($slot && $slot->booked_count > 0) {
                    $slot->decrement('booked_count');
                }
            }

            // Kirim notifikasi ke Guru BK
            \App\Helpers\NotificationHelper::notifyGuruBkBookingCanceledBySiswa($booking, $cancelReason);
        });

        return response()->json(['ok' => true]);
    }
}
