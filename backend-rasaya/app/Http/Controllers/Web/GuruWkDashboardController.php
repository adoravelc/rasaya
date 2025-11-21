<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AnalisisEntry;
use Illuminate\Http\Request;

class GuruWkDashboardController extends Controller
{
    public function index(\Illuminate\Http\Request $request){
        // Saring hanya siswa di kelas wali ini (tahun ajaran terbaru)
        $userId = $request->user()->id;
        
        // Siswa butuh perhatian (merah): needs_attention=true dan handling_status=NULL
        $attentionList = AnalisisEntry::with(['siswaKelas.siswa.user', 'siswaKelas.kelas.jurusan'])
            ->where('needs_attention', true)
            ->whereNull('handling_status')
            ->whereHas('siswaKelas.kelas', function($qq) use ($userId){
                $qq->where('wali_guru_id', $userId);
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->unique('siswa_kelas_id')
            ->take(20)
            ->values();
        
        // Siswa sedang ditangani (orange): needs_attention=true dan handling_status='handled'
        $handledList = AnalisisEntry::with(['siswaKelas.siswa.user', 'siswaKelas.kelas.jurusan'])
            ->where('needs_attention', true)
            ->where('handling_status', 'handled')
            ->whereHas('siswaKelas.kelas', function($qq) use ($userId){
                $qq->where('wali_guru_id', $userId);
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->unique('siswa_kelas_id')
            ->take(20)
            ->values();

        // Jadwal konseling siswa di kelas wali (read-only untuk reminder)
        $kelasId = \App\Models\Kelas::where('wali_guru_id', $userId)
            ->latest('tahun_ajaran_id')
            ->value('id');
        
        $konselingSchedules = collect();
        if ($kelasId) {
            $konselingSchedules = \App\Models\SlotBooking::with([
                'slot.guru.user',
                'siswaKelas.siswa.user',
                'siswaKelas.kelas'
            ])
            ->whereHas('siswaKelas', function($q) use ($kelasId) {
                $q->where('kelas_id', $kelasId);
            })
            ->whereHas('slot', function($q) {
                $q->where('status', 'published')
                  ->where('start_at', '>=', now())
                  ->where('start_at', '<=', now()->addDays(7));
            })
            ->whereIn('status', ['booked', 'held'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        }

        return view('roles.guru.wali_kelas.dashboard', [
            'attentionList' => $attentionList,
            'handledList' => $handledList,
            'konselingSchedules' => $konselingSchedules,
        ]);
    }
}

