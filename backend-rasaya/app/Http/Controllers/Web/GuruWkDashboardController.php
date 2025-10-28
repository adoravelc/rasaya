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
        $rows = AnalisisEntry::with(['siswaKelas.siswa.user', 'siswaKelas.kelas.jurusan'])
            ->where('needs_attention', true)
            ->whereHas('siswaKelas.kelas', function($qq) use ($userId){
                $qq->where('wali_guru_id', $userId);
            })
            ->latest()
            ->get()
            ->unique('siswa_kelas_id')
            ->take(20)
            ->values();

        return view('roles.guru.wali_kelas.dashboard', [
            'attentionList' => $rows,
        ]);
    }
}

