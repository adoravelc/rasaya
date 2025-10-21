<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\SiswaKelas;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AdminSiswaKelasController extends Controller
{
    public function index(Request $request)
    {
        $tahunAjarans = TahunAjaran::orderByDesc('nama')->get();
        $activeTa = $request->input('tahun_ajaran_id') ?: TahunAjaran::aktif()->value('id');

        $kelas = Kelas::with(['waliGuru'])
            ->when($activeTa, fn($q) => $q->where('tahun_ajaran_id', $activeTa))
            ->orderBy('tingkat')->orderByRaw("COALESCE(penjurusan,'')")->orderBy('rombel')
            ->get();

        // Ambil mapping siswa->kelas aktif
        $assignments = SiswaKelas::with(['siswa.user', 'kelas'])
            ->when($activeTa, fn($q) => $q->where('tahun_ajaran_id', $activeTa))
            ->where('is_active', true)
            ->get();

        $siswas = Siswa::with('user')->orderBy('user_id')->get();

        return view('roles.admin.siswa_kelas.index', compact('tahunAjarans', 'activeTa', 'kelas', 'assignments', 'siswas'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'exists:tahun_ajarans,id'],
            'kelas_id' => ['required', 'exists:kelass,id'],
            'siswa_id' => ['required', 'exists:siswas,user_id'],
        ]);

        $row = SiswaKelas::firstOrCreate([
            'tahun_ajaran_id' => $data['tahun_ajaran_id'],
            'kelas_id' => $data['kelas_id'],
            'siswa_id' => $data['siswa_id'],
        ], [
            'is_active' => true,
            'joined_at' => Carbon::now()->toDateString(),
        ]);

        return back()->with('success', 'Siswa ditambahkan ke kelas.');
    }

    public function remove(Request $request)
    {
        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'exists:tahun_ajarans,id'],
            'kelas_id' => ['required', 'exists:kelass,id'],
            'siswa_id' => ['required', 'exists:siswas,user_id'],
        ]);

        $row = SiswaKelas::where($data)->first();
        if ($row) {
            $row->is_active = false;
            $row->left_at = Carbon::now()->toDateString();
            $row->save();
        }
        return back()->with('success', 'Siswa dikeluarkan dari kelas.');
    }
}
