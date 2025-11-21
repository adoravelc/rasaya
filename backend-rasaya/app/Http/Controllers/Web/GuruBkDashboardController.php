<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AnalisisEntry;

class GuruBkDashboardController extends Controller
{
    public function index(){
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

        return view('roles.guru.guru_bk.dashboard', [
            'attentionList' => $attentionList,
            'handledList' => $handledList,
        ]);
    }
}