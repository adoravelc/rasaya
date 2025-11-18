<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InputSiswa;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;

class GuruRefleksiHistoryController extends Controller
{
    public function index(Request $request)
    {
        $years = TahunAjaran::orderByDesc('id')->get();
        $active = TahunAjaran::where('is_active', true)->first();
        $yearId = (int)($request->query('year_id', $active?->id));

        $q = InputSiswa::with(['siswaKelas.siswa.user','siswaDilaporKelas.siswa.user'])
            ->whereHas('siswaKelas', function($qq) use ($yearId){
                $qq->where('tahun_ajaran_id', $yearId);
            })
            ->orderByDesc('tanggal')
            ->orderByDesc('id');

        if ($search = trim((string)$request->query('q'))) {
            $q->where(function($w) use ($search){
                $w->where('teks', 'like', "%$search%")
                  ->orWhereHas('siswaKelas.siswa.user', function($u) use ($search){
                      $u->where('name','like',"%$search%")
                        ->orWhere('identifier','like',"%$search%");
                  });
            });
        }

        $rows = $q->paginate(20);
        return view('roles.guru.refleksi.history', compact('rows','years','yearId'));
    }
}
