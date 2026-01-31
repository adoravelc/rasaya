<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AnalisisEntry;
use App\Models\InputGuru;
use App\Models\SlotKonseling;
use App\Models\SlotBooking;
use Illuminate\Support\Facades\Auth;
use App\Models\CounselingReferral;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class GuruBkDashboardController extends Controller
{
    public function index(){
        /** @var \App\Models\User $user */
        $user = Auth::user();
        // Pisahkan siswa butuh perhatian (belum ditangani) dan sedang ditangani
        
        // Siswa butuh perhatian (merah): needs_attention=true dan handling_status=NULL
        $attentionList = AnalisisEntry::with(['siswaKelas.siswa.user', 'siswaKelas.kelas.jurusan'])
            ->where('needs_attention', true)
            ->whereNull('handling_status')
            ->orderBy('created_at', 'asc')
            ->get()
            ->unique('siswa_kelas_id')
            ->take(20)
            ->values();
        
        // Siswa sedang ditangani (orange): needs_attention=true dan handling_status='handled'
        $handledList = AnalisisEntry::with(['siswaKelas.siswa.user', 'siswaKelas.kelas.jurusan'])
            ->where('needs_attention', true)
            ->where('handling_status', 'handled')
            ->orderBy('created_at', 'asc')
            ->get()
            ->unique('siswa_kelas_id')
            ->take(20)
            ->values();

        // Jadwal konseling mendatang (upcoming bookings untuk guru BK ini)
        $guru = $user->guru;
        $guruId = $guru ? $guru->user_id : null;
        $upcomingSchedules = collect();
        $acceptedUnscheduledReferrals = collect();
        
        if ($guruId) {
            $upcomingSchedules = SlotBooking::with(['slot', 'siswaKelas.siswa.user', 'siswaKelas.kelas.jurusan'])
                ->whereHas('slot', function($q) use ($guruId) {
                    $q->where('guru_id', $guruId)
                      ->where('status', 'published')
                      ->where('start_at', '>=', now())
                      ->where('start_at', '<=', now()->addDays(7)); // 7 hari ke depan
                })
                ->where('status', 'booked')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // Referral yang sudah diterima oleh Guru BK ini namun belum dijadwalkan (belum ada slot/booking)
            $acceptedUnscheduledReferrals = CounselingReferral::with(['siswaKelas.siswa.user','submittedBy'])
                ->accepted()
                ->where('accepted_by_user_id', $user->id)
                ->whereNull('slot_konseling_id')
                ->orderByDesc('accepted_at')
                ->limit(15)
                ->get();
        }

        // Pending referrals (belum diterima) ditampilkan untuk Guru BK
        $pendingReferrals = CounselingReferral::with(['siswaKelas.siswa.user','submittedBy'])
            ->pending()
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        return view('roles.guru.guru_bk.dashboard', [
            'attentionList' => $attentionList,
            'handledList' => $handledList,
            'upcomingSchedules' => $upcomingSchedules,
            'pendingReferrals' => $pendingReferrals,
            'acceptedUnscheduledReferrals' => $acceptedUnscheduledReferrals,
        ]);
    }

    /**
     * Update status booking konseling
     * Guru BK bisa mengubah status booking (canceled, completed, no_show)
     */
    public function updateBookingStatus($bookingId, \Illuminate\Http\Request $request)
    {
        $request->validate([
            'status' => 'required|in:canceled,completed,no_show',
            'cancel_reason' => 'required_if:status,canceled|nullable|string|max:255'
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $guru = $user->guru;

        if (!$guru) {
            return redirect()->back()->with('error', 'Anda tidak terdaftar sebagai guru.');
        }

        // Cari booking dan pastikan ini slot milik guru BK yang sedang login
        $booking = SlotBooking::with(['slot', 'siswaKelas.siswa.user'])->findOrFail($bookingId);

        if ($booking->slot->guru_id !== $guru->user_id) {
            return redirect()->back()->with('error', 'Anda tidak memiliki akses untuk mengubah booking ini.');
        }

        $oldStatus = $booking->status;
        $newStatus = $request->status;

        // Validasi: no_show hanya bisa dipilih jika sudah lewat jam mulai konseling
        if ($newStatus === 'no_show') {
            $nowWita = now()->setTimezone('Asia/Makassar');
            if ($booking->slot->start_at->greaterThan($nowWita)) {
                return redirect()->back()->with('error', 'Status "No Show" hanya bisa dipilih setelah waktu konseling dimulai.');
            }
        }

        // Validasi: canceled harus ada alasan
        if ($newStatus === 'canceled' && !$request->filled('cancel_reason')) {
            return redirect()->back()->with('error', 'Alasan pembatalan wajib diisi.');
        }

        // Update status
        $booking->status = $newStatus;

        // Jika status canceled, simpan alasan pembatalan dan kirim notifikasi ke siswa
        if ($newStatus === 'canceled') {
            $booking->cancel_reason = $request->cancel_reason;
            $booking->canceled_by_user_id = $user->id; // Track siapa yang cancel
            
            // Kirim notifikasi ke siswa
            $siswaUser = $booking->siswaKelas->siswa->user ?? null;
            if ($siswaUser) {
                \App\Helpers\NotificationHelper::notifyBookingCanceled($booking, $request->cancel_reason);
            }
        }

        $booking->save();
        
        return redirect()->back()->with('success', "Status booking berhasil diubah menjadi '{$newStatus}'.");
    }

    /**
     * JSON: daftar booking yang sudah selesai pada tanggal tertentu,
     * dilengkapi status observasi (InputGuru) untuk siswa tsb.
     *
     * GET /guru/bk/bookings/completed?date=YYYY-MM-DD
     */
    public function completedBookings(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $guru = $user->guru;

        if (!$guru) {
            return response()->json(['message' => 'Anda tidak terdaftar sebagai guru.'], 403);
        }

        $tz = 'Asia/Makassar';
        $date = $request->input('date');
        $targetDate = $date ? Carbon::parse($date, $tz)->toDateString() : now($tz)->toDateString();

        // Ambil semua booking completed milik guru ini pada tanggal slot tsb
        $bookings = SlotBooking::with([
                'slot',
                'siswaKelas.siswa.user',
                'siswaKelas.kelas.jurusan',
                'siswaKelas.tahunAjaran',
            ])
            ->where('status', 'completed')
            ->whereHas('slot', function ($q) use ($guru, $targetDate) {
                $q->where('guru_id', $guru->user_id)
                  ->whereDate('tanggal', $targetDate);
            })
            ->orderByDesc('id')
            ->get();

        // Map observasi hari itu (unik per guru+siswa_kelas+tanggal)
        $observasiMap = InputGuru::query()
            ->where('guru_id', $guru->user_id)
            ->whereDate('tanggal', $targetDate)
            ->get()
            ->keyBy('siswa_kelas_id');

        $rows = $bookings->map(function (SlotBooking $b) use ($observasiMap, $targetDate) {
            $sk = $b->siswaKelas;
            $skId = (int) ($b->siswa_kelas_id ?? 0);
            $siswaNama = data_get($sk, 'siswa.user.name') ?? data_get($sk, 'siswa.nama') ?? '-';
            $kelasLabel = data_get($sk, 'kelas.label')
                ?? trim(implode(' ', array_filter([
                    data_get($sk, 'kelas.tingkat'),
                    data_get($sk, 'kelas.jurusan.nama'),
                    data_get($sk, 'kelas.rombel'),
                ])))
                ?? '-';

            $slotStart = optional($b->slot)->start_at;
            $startWita = $slotStart ? $slotStart->copy()->setTimezone('Asia/Makassar')->format('H:i') : '-';
            $endWita = optional($b->slot)->end_at ? $b->slot->end_at->copy()->setTimezone('Asia/Makassar')->format('H:i') : '-';
            $jam = ($startWita !== '-' && $endWita !== '-') ? "{$startWita}–{$endWita} WITA" : '-';

            $obs = $observasiMap->get($skId);
            $obsId = $obs?->id;

            return [
                'booking_id' => $b->id,
                'slot_id' => $b->slot_id,
                'tanggal' => $targetDate,
                'jam' => $jam,
                'siswa_kelas_id' => $skId,
                'siswa_nama' => $siswaNama,
                'kelas_label' => $kelasLabel,
                'observasi_filled' => (bool) $obsId,
                'observasi_id' => $obsId,
                'observasi_create_url' => route('guru.observasi.index', [
                    'open_create' => 1,
                    'siswa_kelas_id' => $skId,
                ]),
                'observasi_edit_url' => $obsId ? route('guru.observasi.index', [
                    'open_edit_id' => $obsId,
                ]) : null,
            ];
        })->values();

        return response()->json([
            'date' => $targetDate,
            'total' => $rows->count(),
            'data' => $rows,
        ]);
    }
}