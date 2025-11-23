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
                ->whereIn('status', ['booked', 'held'])
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
}