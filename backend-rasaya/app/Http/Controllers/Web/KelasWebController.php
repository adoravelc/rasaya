<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KelasWebController extends Controller
{
    public function index(Request $request)
    {
        $tahunAjarans = TahunAjaran::orderByDesc('nama')->get();
        $activeTa = $request->input('tahun_ajaran_id') ?: TahunAjaran::aktif()->value('id');

        $kelas = Kelas::with(['tahunAjaran', 'waliGuru'])
            ->when($activeTa, fn($q) => $q->where('tahun_ajaran_id', $activeTa))
            ->orderBy('tingkat')
            ->orderByRaw("COALESCE(penjurusan,'')") // supaya null diurutkan stabil
            ->orderBy('rombel')
            ->paginate(15);

        $trashed = Kelas::onlyTrashed()
            ->when($activeTa, fn($q) => $q->where('tahun_ajaran_id', $activeTa))
            ->orderBy('tingkat')
            ->orderByRaw("COALESCE(penjurusan,'')")
            ->orderBy('rombel')
            ->get();

        return view('roles.admin.kelas.index', compact('kelas', 'tahunAjarans', 'activeTa', 'trashed'));
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
            'penjurusan' => ['nullable', 'in:IPA,IPS'],
            'rombel' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('kelass', 'rombel')->where(
                    fn($q) =>
                    $q->where('tahun_ajaran_id', $ta)
                        ->where('tingkat', $ting)
                        ->where('penjurusan', $jur ?? null)
                ),
            ],
            'kurikulum' => ['nullable', 'in:K13,KURMER'],
            'wali_guru_id' => ['nullable', 'exists:users,id'],
        ]);

        $row = Kelas::create($data)->load(['tahunAjaran', 'waliGuru']);
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
            'penjurusan' => ['nullable', 'in:IPA,IPS'],
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
                            ->where('penjurusan', $jur ?? null)
                    ),
            ],
            'kurikulum' => ['nullable', 'in:K13,KURMER'],
            'wali_guru_id' => ['nullable', 'exists:users,id'],
        ]);

        $kelas->update($data);
        return response()->json(['ok' => true, 'data' => $kelas->fresh()->load(['tahunAjaran', 'waliGuru'])]);
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
