<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InputSiswa;
use App\Models\Kelas;
use App\Models\SiswaKelas;

class GuruRefleksiController extends Controller
{
    public function index(Request $r)
    {
        $user = $r->user();
        $guruJenis = optional($user->guru)->jenis; // 'bk' | 'wali_kelas'

        $q = InputSiswa::with([
            'siswaKelas.siswa.user',
            'siswaKelas.kelas.jurusan',
            'siswaDilaporKelas.siswa.user',
            'siswaDilaporKelas.kelas.jurusan'
        ])->latest();

        // Restrict for Wali Kelas: hanya kelas wali ini (tahun ajaran terbaru)
        if ($guruJenis === 'wali_kelas') {
            $wkKelasId = Kelas::where('wali_guru_id', $user->id)->latest('tahun_ajaran_id')->value('id');
            if ($wkKelasId) {
                $q->whereHas('siswaKelas', fn($qq) => $qq->where('kelas_id', $wkKelasId));
            } else {
                // jika belum punya kelas aktif, kembalikan kosong
                $q->whereRaw('1=0');
            }
        }

        // Filter kelas (hanya BK yang boleh memilih lintas kelas)
        $kelasFilter = (int) $r->input('kelas_id', 0);
        if ($kelasFilter && $guruJenis === 'bk') {
            $q->whereHas('siswaKelas', fn($qq) => $qq->where('kelas_id', $kelasFilter));
        }

        // Filter jenis refleksi
        $jenis = $r->input('jenis', ''); // 'pribadi' | 'teman'
        if ($jenis === 'pribadi') {
            $q->where('is_friend', false);
        } elseif ($jenis === 'teman') {
            $q->where('is_friend', true);
        }

        // Pencarian: nama siswa, identifier, atau teks refleksi
        if ($r->filled('q')) {
            $term = trim((string)$r->input('q'));
            $like = '%'.$term.'%';
            $q->where(function($qq) use ($like) {
                $qq->where('teks', 'like', $like)
                   ->orWhereHas('siswaKelas.siswa.user', fn($u) => $u->where('name', 'like', $like)->orWhere('identifier','like',$like))
                   ->orWhereHas('siswaDilaporKelas.siswa.user', fn($u) => $u->where('name', 'like', $like)->orWhere('identifier','like',$like));
            });
        }

        $rows = $q->paginate(25)->withQueryString();

        // Kelas options (hanya untuk BK)
        $kelasOptions = collect();
        if ($guruJenis === 'bk') {
            $kelasOptions = Kelas::with('tahunAjaran')
                ->orderBy('tahun_ajaran_id','desc')
                ->orderBy('tingkat')
                ->orderBy('rombel')
                ->get()
                ->map(fn($k) => [ 'id'=>$k->id, 'label'=>$k->label.' — '.($k->tahunAjaran->nama ?? '-') ]);
        }

        $filters = [
            'q' => (string)$r->input('q',''),
            'kelas_id' => $kelasFilter ? (string)$kelasFilter : '',
            'jenis' => (string)$jenis,
        ];

        return view('roles.guru.refleksi.index', compact('rows','filters','kelasOptions','guruJenis'));
    }
}
