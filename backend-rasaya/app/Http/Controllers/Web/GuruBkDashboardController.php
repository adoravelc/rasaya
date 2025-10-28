<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AnalisisEntry;

class GuruBkDashboardController extends Controller
{
    public function index(){
        // Ambil daftar siswa yang butuh perhatian (unique per siswa, ambil analisis terbaru)
        $rows = AnalisisEntry::with(['siswaKelas.siswa.user', 'siswaKelas.kelas.jurusan'])
            ->where('needs_attention', true)
            ->latest()
            ->get()
            ->unique('siswa_kelas_id')
            ->take(20)
            ->values();

        return view('roles.guru.guru_bk.dashboard', [
            'attentionList' => $rows,
        ]);
    }
}