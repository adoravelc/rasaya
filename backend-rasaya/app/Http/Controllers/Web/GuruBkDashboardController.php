<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AnalisisEntry;
use App\Models\SlotKonseling;
use App\Models\SlotBooking;
use Illuminate\Support\Facades\Auth;
use App\Models\CounselingReferral;

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
}