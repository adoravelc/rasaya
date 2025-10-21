<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\Siswa;
use App\Models\SiswaKelas;
use App\Models\Jurusan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KelasWebController extends Controller
{
    public function index(Request $request)
    {
        $tahunAjarans = TahunAjaran::orderByDesc('nama')->get();
        $activeTa = $request->input('tahun_ajaran_id') ?: TahunAjaran::aktif()->value('id');

    $kelas = Kelas::with(['tahunAjaran', 'waliGuru','jurusan'])
            ->when($activeTa, fn($q) => $q->where('tahun_ajaran_id', $activeTa))
            ->orderBy('tingkat')
        ->orderByRaw("COALESCE(jurusan_id,0)") // supaya null diurutkan stabil
            ->orderBy('rombel')
            ->paginate(15);

        $trashed = Kelas::onlyTrashed()
            ->when($activeTa, fn($q) => $q->where('tahun_ajaran_id', $activeTa))
            ->orderBy('tingkat')
            ->orderByRaw("COALESCE(jurusan_id,0)")
            ->orderBy('rombel')
            ->get();

        // Ambil daftar wali kelas (user) untuk dropdown
        $waliOptions = \App\Models\User::query()
            ->whereHas('guru', function ($q) { $q->where('jenis', 'wali_kelas'); })
            ->orderBy('name')
            ->get(['id', 'name']);

        // Data untuk manajemen siswa per kelas (integrasi)
        $siswas = Siswa::with('user')->orderBy('user_id')->get();
        $assignments = SiswaKelas::with(['siswa.user'])
            ->when($activeTa, fn($q) => $q->where('tahun_ajaran_id', $activeTa))
            ->where('is_active', true)
            ->get();

    $jurusans = Jurusan::where('tahun_ajaran_id', $activeTa)->orderBy('nama')->get();

    return view('roles.admin.kelas.index', compact('kelas', 'tahunAjarans', 'activeTa', 'trashed', 'waliOptions', 'siswas', 'assignments', 'jurusans'));
    }

    // ===== AJAX (JSON) =====
    public function storeAjax(Request $request)
    {
        $ta = $request->input('tahun_ajaran_id');
        $ting = $request->input('tingkat');
        $jur = $request->input('penjurusan'); // nullable
        $rom = $request->input('rombel');

        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'exists:tahun_ajarans,id'],
            'tingkat' => ['required', 'in:X,XI,XII'],
            'jurusan_id' => ['nullable','integer', Rule::exists('jurusans','id')->where(fn($q)=>$q->where('tahun_ajaran_id',$ta)->whereNull('deleted_at'))],
            'rombel' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('kelass', 'rombel')->where(
                    fn($q) =>
                    $q->where('tahun_ajaran_id', $ta)
                        ->where('tingkat', $ting)
                        ->where('jurusan_id', $request->input('jurusan_id'))
                ),
            ],
            'kurikulum' => ['nullable', 'in:K13,KURMER'],
            'wali_guru_id' => ['required', 'exists:users,id'],
        ]);

    $row = Kelas::create($data)->load(['tahunAjaran', 'waliGuru','jurusan']);
        return response()->json(['ok' => true, 'data' => $row], 201);
    }

    public function updateAjax(Request $request, Kelas $kelas)
    {
        $ta = $request->input('tahun_ajaran_id');
        $ting = $request->input('tingkat');
        $jur = $request->input('penjurusan');
        $rom = $request->input('rombel');

        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'exists:tahun_ajarans,id'],
            'tingkat' => ['required', 'in:X,XI,XII'],
            'jurusan_id' => ['nullable','integer', Rule::exists('jurusans','id')->where(fn($q)=>$q->where('tahun_ajaran_id',$ta)->whereNull('deleted_at'))],
            'rombel' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('kelass', 'rombel')
                    ->ignore($kelas->id)
                    ->where(
                        fn($q) =>
                        $q->where('tahun_ajaran_id', $ta)
                            ->where('tingkat', $ting)
                            ->where('jurusan_id', $request->input('jurusan_id'))
                    ),
            ],
            'kurikulum' => ['nullable', 'in:K13,KURMER'],
            'wali_guru_id' => ['required', 'exists:users,id'],
        ]);

        $kelas->update($data);
    return response()->json(['ok' => true, 'data' => $kelas->fresh()->load(['tahunAjaran', 'waliGuru','jurusan'])]);
    }

    public function destroyAjax(Kelas $kelas)
    {
        $kelas->delete();
        return response()->json(['ok' => true]);
    }

    public function restore($id)
    {
        Kelas::onlyTrashed()->findOrFail($id)->restore();
        return response()->json(['ok' => true]);
    }

    public function forceDelete($id)
    {
        Kelas::onlyTrashed()->findOrFail($id)->forceDelete();
        return response()->json(['ok' => true]);
    }
}
