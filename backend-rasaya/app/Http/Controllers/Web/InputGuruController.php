<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InputGuru;
use App\Models\SiswaKelas;
use App\Models\KategoriMasalah;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;

class InputGuruController extends Controller
{
    public function index(Request $r)
    {
        $q = InputGuru::with(['siswaKelas.siswa', 'siswaKelas.kelas', 'kategoris'])->latest();

        if ($r->filled('kelas_id')) {
            $kelasId = (int) $r->kelas_id;
            $q->whereHas('siswaKelas', fn($q2) => $q2->where('kelas_id', $kelasId));
        }

        $rows = $q->paginate(15)->withQueryString();

        $siswaKelas = SiswaKelas::with(['siswa', 'kelas', 'tahunAjaran'])
            ->where('is_active', true)
            ->orderBy('kelas_id')
            ->get()
            ->map(fn($sk) => ['id' => $sk->id, 'label' => $sk->label]);

        $opsiKondisi = ['green', 'yellow', 'orange', 'red', 'black', 'grey'];
        $kategoris = KategoriMasalah::orderBy('nama')->get(['id', 'nama']);

        return view('roles.guru.observasi.index', compact('rows', 'siswaKelas', 'kategoris', 'opsiKondisi'));
    }

    public function store(Request $r): JsonResponse
    {
        $r->validate([
            'siswa_kelas_id' => ['required', 'integer', Rule::exists('siswa_kelass', 'id')],
            'teks' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'tanggal' => ['nullable', 'date'],
            'kondisi_siswa' => ['required', Rule::in(['green', 'yellow', 'orange', 'red', 'black', 'grey'])],
            'gambar' => ['nullable', 'string', 'max:255'],
            'kategori_ids' => ['array'],
            'kategori_ids.*' => [Rule::exists('kategori_masalahs', 'id')],
        ]);

        // ambil id guru dari relasi; fallback ke user->id (karena gurus.user_id == users.id)
        $guruId = data_get($r->user(), 'guru.user_id') ?? $r->user()->id;
        abort_if(!$guruId, 403, 'Guru tidak valid');

        $teks = $r->input('teks');
        if ($teks === null || $teks === '')
            $teks = $r->input('catatan', '');

        $row = InputGuru::create([
            'guru_id' => $guruId,
            'siswa_kelas_id' => (int) $r->siswa_kelas_id,
            'tanggal' => $r->input('tanggal') ?: now()->toDateString(),
            'teks' => $teks,
            'gambar' => $r->input('gambar'),
            'kondisi_siswa' => $r->input('kondisi_siswa'),
        ]);

        if ($r->filled('kategori_ids')) {
            $row->kategoris()->sync($r->input('kategori_ids', []));
        }

        return response()->json($row->load('siswaKelas.siswa', 'siswaKelas.kelas', 'kategoris'), 201);
    }


    public function show(InputGuru $observasi): JsonResponse
    {
        return response()->json($observasi->load('siswaKelas.siswa', 'siswaKelas.kelas', 'kategoris'));
    }

    public function update(Request $r, InputGuru $observasi): JsonResponse
    {
        $r->validate([
            'siswa_kelas_id' => ['sometimes', 'integer', Rule::exists('siswa_kelass', 'id')],
            'teks' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'tanggal' => ['nullable', 'date'],
            'kondisi_siswa' => ['sometimes', Rule::in(['green', 'yellow', 'orange', 'red', 'black', 'grey'])],
            'gambar' => ['nullable', 'string', 'max:255'],
            'kategori_ids' => ['array'],
            'kategori_ids.*' => [Rule::exists('kategori_masalahs', 'id')],
        ]);

        $data = $r->only(['siswa_kelas_id', 'tanggal', 'kondisi_siswa', 'gambar']);
        $teks = $r->input('teks');
        if (($teks === null || $teks === '') && $r->filled('catatan')) {
            $teks = $r->input('catatan');
        }
        if ($teks !== null) {
            $data['teks'] = $teks;
        }

        $observasi->update($data);

        if ($r->has('kategori_ids')) {
            $observasi->kategoris()->sync($r->input('kategori_ids', []));
        }

        return response()->json($observasi->load('siswaKelas.siswa', 'siswaKelas.kelas', 'kategoris'));
    }

    public function destroy(InputGuru $observasi)
    {
        $observasi->delete();
        return response()->noContent();
    }

    // optional: soft delete helpers
    public function trashed(): JsonResponse
    {
        $rows = InputGuru::onlyTrashed()->latest()->paginate(15);
        return response()->json($rows);
    }
    public function restore($id): JsonResponse
    {
        $row = InputGuru::onlyTrashed()->findOrFail($id);
        $row->restore();
        return response()->json(['ok' => true]);
    }
    public function force($id): JsonResponse
    {
        $row = InputGuru::onlyTrashed()->findOrFail($id);
        $row->forceDelete();
        return response()->json(['ok' => true]);
    }
}
