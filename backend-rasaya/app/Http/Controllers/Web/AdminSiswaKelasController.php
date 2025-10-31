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
        $kelasId = $request->input('kelas_id');
        $search = trim((string) $request->input('search'));

        // All kelas options for dropdown
        $kelasOptions = Kelas::with(['waliGuru'])
            ->when($activeTa, fn($q) => $q->where('tahun_ajaran_id', $activeTa))
            ->orderBy('tingkat')
            ->orderByRaw('COALESCE(jurusan_id, 0)')
            ->orderBy('rombel')
            ->get();

        // Selected class (if any)
        $selectedKelas = $kelasId ? $kelasOptions->firstWhere('id', $kelasId) : null;

        // Roster for selected class only (when kelas_id is provided)
        $assignments = $kelasId 
            ? SiswaKelas::with(['siswa.user'])
                ->where('tahun_ajaran_id', $activeTa)
                ->where('kelas_id', $kelasId)
                ->where('is_active', true)
                ->orderBy('siswa_id')
                ->get()
            : collect();

        // Search results (all students across all classes in active TA, if search is provided)
        $searchResults = collect();
        if ($search) {
            $searchResults = SiswaKelas::with(['siswa.user', 'kelas'])
                ->where('tahun_ajaran_id', $activeTa)
                ->where('is_active', true)
                ->whereHas('siswa.user', function($q) use ($search) {
                    $like = "%{$search}%";
                    $q->where('name', 'like', $like)
                      ->orWhere('identifier', 'like', $like);
                })
                ->orderBy('kelas_id')
                ->orderBy('siswa_id')
                ->get();
        }

        // Available students for "Tambah Siswa" (exclude students already in ANY class for this TA)
        $registeredSiswaIds = SiswaKelas::where('tahun_ajaran_id', $activeTa)
            ->where('is_active', true)
            ->pluck('siswa_id');
        
        $availableSiswas = Siswa::with('user')
            ->whereNotIn('user_id', $registeredSiswaIds)
            ->orderBy('user_id')
            ->get();

        return view('roles.admin.siswa_kelas.index', compact('tahunAjarans', 'activeTa', 'kelasOptions', 'kelasId', 'selectedKelas', 'assignments', 'availableSiswas', 'search', 'searchResults'));
    }

    // Full-page roster/daftar hadir style view
    public function full(Request $request)
    {
        $tahunAjarans = TahunAjaran::orderByDesc('nama')->get();
        $activeTa = $request->input('tahun_ajaran_id') ?: TahunAjaran::aktif()->value('id');
        $kelasId = $request->input('kelas_id');

        // Options for dropdown (all classes in active TA)
        $kelasOptions = Kelas::with('waliGuru')
            ->when($activeTa, fn($q) => $q->where('tahun_ajaran_id', $activeTa))
            ->orderBy('tingkat')
            ->orderByRaw('COALESCE(jurusan_id, 0)')
            ->orderBy('rombel')
            ->get();

        // Page content: either all classes or a single class when filtered
        $kelas = (clone $kelasOptions)
            ->when($kelasId, fn($q) => $q->where('id', $kelasId));

        // Fetch roster per kelas with siswa user eager-loaded
        $assignments = SiswaKelas::with(['siswa.user'])
            ->when($activeTa, fn($q) => $q->where('tahun_ajaran_id', $activeTa))
            ->when($kelasId, fn($q) => $q->where('kelas_id', $kelasId))
            ->where('is_active', true)
            ->orderBy('kelas_id')
            ->orderBy('siswa_id')
            ->get()
            ->groupBy('kelas_id');

        return view('roles.admin.siswa_kelas.full', compact('tahunAjarans', 'activeTa', 'kelasOptions', 'kelasId', 'kelas', 'assignments'));
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
