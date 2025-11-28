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

        // 1. Base Query
        $q = InputSiswa::with([
            'siswaKelas.siswa.user',
            'siswaKelas.kelas.jurusan',
            'siswaDilaporKelas.siswa.user',
            'siswaDilaporKelas.kelas.jurusan'
        ]);

        // Variabel untuk menyimpan ID kelas yang sedang aktif (untuk keperluan filter siswa)
        $activeKelasId = null;

        // 2. Logic Wali Kelas: Kunci ke kelas sendiri
        if ($guruJenis === 'wali_kelas') {
            $wkKelasId = Kelas::where('wali_guru_id', $user->id)
                ->latest('tahun_ajaran_id') // Asumsi mengambil tahun ajaran terakhir
                ->value('id');

            if ($wkKelasId) {
                $q->whereHas('siswaKelas', fn($qq) => $qq->where('kelas_id', $wkKelasId));
                $activeKelasId = $wkKelasId;
            } else {
                // Jika tidak punya kelas, result kosong
                $q->whereRaw('1=0');
            }
        }

        // 3. Logic Guru BK: Filter Kelas
        $kelasFilter = (int) $r->input('kelas_id', 0);
        if ($guruJenis === 'bk') {
            if ($kelasFilter) {
                $q->whereHas('siswaKelas', fn($qq) => $qq->where('kelas_id', $kelasFilter));
                $activeKelasId = $kelasFilter;
            }
        }

        // 4. Logic Filter Siswa (Hanya aktif jika ada activeKelasId)
        $siswaFilter = (int) $r->input('siswa_id', 0);
        $siswaOptions = collect();

        if ($activeKelasId) {
            // Ambil daftar siswa di kelas tersebut untuk dropdown
            $siswaOptions = SiswaKelas::with('siswa.user')
                ->where('kelas_id', $activeKelasId)
                ->where('is_active', true) // Asumsi hanya siswa aktif
                ->get()
                ->sortBy('siswa.user.name')
                ->map(fn($sk) => [
                    'id' => $sk->siswa_id, // Kita filter by ID Siswa (profile), bukan ID SiswaKelas agar lebih konsisten
                    'label' => optional(optional($sk->siswa)->user)->name ?? '-'
                ]);
            
            // Terapkan filter siswa jika dipilih
            if ($siswaFilter) {
                $q->whereHas('siswaKelas', fn($qq) => $qq->where('siswa_id', $siswaFilter));
            }
        }

        // 5. Filter Jenis (Pribadi / Teman)
        $jenis = $r->input('jenis', ''); // 'pribadi' | 'teman'
        if ($jenis === 'pribadi') {
            $q->where('is_friend', false);
        } elseif ($jenis === 'teman') {
            $q->where('is_friend', true);
        }

        // 6. Pencarian (Nama / Identifier / Konten)
        if ($r->filled('q')) {
            $term = trim((string)$r->input('q'));
            $like = '%'.$term.'%';
            $q->where(function($qq) use ($like) {
                $qq->where('teks', 'like', $like)
                   ->orWhereHas('siswaKelas.siswa.user', fn($u) => $u->where('name', 'like', $like)->orWhere('identifier','like',$like))
                   ->orWhereHas('siswaDilaporKelas.siswa.user', fn($u) => $u->where('name', 'like', $like)->orWhere('identifier','like',$like));
            });
        }

        // 7. Sorting (REQUEST USER: Tanggal Terbaru -> Inputan Terbaru)
        $q->orderBy('tanggal', 'desc')
          ->orderBy('created_at', 'desc');

        // 8. Execute
        $rows = $q->paginate(25)->withQueryString();

        // 9. Data Tambahan untuk View
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
            'q'        => (string)$r->input('q',''),
            'kelas_id' => $kelasFilter ? (string)$kelasFilter : '',
            'siswa_id' => $siswaFilter ? (string)$siswaFilter : '',
            'jenis'    => (string)$jenis,
        ];

        return view('roles.guru.refleksi.index', compact('rows','filters','kelasOptions','guruJenis','siswaOptions'));
    }
}