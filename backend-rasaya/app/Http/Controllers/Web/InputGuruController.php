<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InputGuru;
use App\Models\SiswaKelas;
use App\Models\KategoriMasalah;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class InputGuruController extends Controller
{
    public function index(Request $r)
    {
        $guruId = data_get($r->user(), 'guru.user_id') ?? $r->user()->id;

        $q = InputGuru::with(['siswaKelas.siswa.user', 'siswaKelas.kelas.jurusan', 'kategoris'])
            ->where('guru_id', $guruId)
            ->latest();

        // Filters & search
        if ($r->filled('kelas_id')) {
            $kelasId = (int) $r->kelas_id;
            $q->whereHas('siswaKelas', fn($q2) => $q2->where('kelas_id', $kelasId));
        }
        if ($r->filled('kondisi')) {
            $q->where('kondisi_siswa', $r->string('kondisi'));
        }
        if ($r->filled('date_from')) {
            $q->whereDate('tanggal', '>=', $r->date('date_from'));
        }
        if ($r->filled('date_to')) {
            $q->whereDate('tanggal', '<=', $r->date('date_to'));
        }
        if ($r->filled('q')) {
            $text = '%'.(string)$r->input('q').'%';
            $q->where('teks', 'like', $text); // khusus cari di catatan saja
        }
        // optional kategori filter (multi)
        $filterKategori = (array) $r->input('filter_kategori_ids', []);
        $filterKategori = array_values(array_filter(array_map('intval', $filterKategori)));
        if (!empty($filterKategori)) {
            $q->whereHas('kategoris', function ($kk) use ($filterKategori) {
                $kk->whereIn('kategori_masalahs.id', $filterKategori);
            });
        }

        $rows = $q->paginate(15)->withQueryString();

        // Determine WK's kelas (if any) for the active TA or latest
        $wkKelasId = \App\Models\Kelas::where('wali_guru_id', $r->user()->id)->latest('tahun_ajaran_id')->value('id');

        // kelas-only options for filter (distinct kelas)
        $kelasOptions = \App\Models\Kelas::with('tahunAjaran')
            ->orderBy('tahun_ajaran_id', 'desc')
            ->orderBy('tingkat')
            ->orderBy('rombel')
            ->get()
            ->map(fn($k) => ['id' => $k->id, 'label' => $k->label . ' — ' . ($k->tahunAjaran->nama ?? '-')]);

        // SiswaKelas options shown in the form: if WK, limit to own kelas; else show all active
        $siswaKelasQ = SiswaKelas::with(['siswa', 'kelas', 'tahunAjaran'])
            ->where('is_active', true);
        if ($wkKelasId) {
            $siswaKelasQ->where('kelas_id', $wkKelasId);
        }
        $siswaKelas = $siswaKelasQ
            ->orderBy('kelas_id')
            ->get()
            ->map(fn($sk) => ['id' => $sk->id, 'label' => $sk->label]);

        $opsiKondisi = ['green', 'yellow', 'orange', 'red', 'black', 'grey'];
        $kategoris = KategoriMasalah::orderBy('nama')->get(['id', 'nama']);

        // Pass filters back to view
        $filters = [
            'q' => (string) $r->input('q',''),
            'kelas_id' => (string) $r->input('kelas_id',''),
            'kondisi' => (string) $r->input('kondisi',''),
            'date_from' => (string) $r->input('date_from',''),
            'date_to' => (string) $r->input('date_to',''),
            'filter_kategori_ids' => $filterKategori,
        ];

    return view('roles.guru.observasi.index', compact('rows', 'siswaKelas', 'kategoris', 'opsiKondisi', 'filters', 'kelasOptions', 'wkKelasId'));
    }

    public function store(Request $r): JsonResponse
    {
        $r->validate([
            'siswa_kelas_id' => ['required', 'integer', Rule::exists('siswa_kelass', 'id')],
            'teks' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'tanggal' => ['nullable', 'date'],
            'kondisi_siswa' => ['required', Rule::in(['green', 'yellow', 'orange', 'red', 'black', 'grey'])],
            'gambar' => ['nullable', 'image', 'max:2048'],
            'kategori_ids' => ['array'],
            'kategori_ids.*' => [Rule::exists('kategori_masalahs', 'id')],
        ]);

        // ambil id guru dari relasi; fallback ke user->id (karena gurus.user_id == users.id)
        $guruId = data_get($r->user(), 'guru.user_id') ?? $r->user()->id;
        abort_if(!$guruId, 403, 'Guru tidak valid');

        // If this user is a wali kelas, restrict siswa_kelas selection to their own kelas
        $wkKelasId = \App\Models\Kelas::where('wali_guru_id', $r->user()->id)->latest('tahun_ajaran_id')->value('id');
        if ($wkKelasId) {
            $sk = SiswaKelas::findOrFail((int)$r->siswa_kelas_id);
            if ($sk->kelas_id !== (int)$wkKelasId) {
                abort(403, 'Anda hanya bisa input untuk siswa di kelas Anda.');
            }
        }

        $teks = $r->input('teks');
        if ($teks === null || $teks === '')
            $teks = $r->input('catatan', '');

        // tanggal target
        $tanggal = $r->input('tanggal') ?: now()->toDateString();

        // duplicate guard: guru + siswa_kelas + tanggal
        $dup = InputGuru::where('guru_id', $guruId)
            ->where('siswa_kelas_id', (int)$r->siswa_kelas_id)
            ->whereDate('tanggal', $tanggal)
            ->first();
        if ($dup) {
            return response()->json([
                'message' => 'Anda sudah mengisi observasi untuk siswa ini pada hari ini. Ingin mengedit yang sudah ada?',
                'existing_id' => $dup->id,
            ], 409);
        }

        // handle image upload (optional)
        $gambarPath = null;
        if ($r->hasFile('gambar')) {
            $gambarPath = $r->file('gambar')->store('observasi', 'public');
        }

        $row = InputGuru::create([
            'guru_id' => $guruId,
            'siswa_kelas_id' => (int) $r->siswa_kelas_id,
            'tanggal' => $tanggal,
            'teks' => $teks,
            'gambar' => $gambarPath,
            'kondisi_siswa' => $r->input('kondisi_siswa'),
        ]);

        if ($r->filled('kategori_ids')) {
            $row->kategoris()->sync($r->input('kategori_ids', []));
        }

        return response()->json($row->load('siswaKelas.siswa', 'siswaKelas.kelas', 'kategoris'), 201);
    }


    public function show(InputGuru $observasi): JsonResponse
    {
    return response()->json($observasi->load('siswaKelas.siswa.user', 'siswaKelas.kelas.jurusan', 'kategoris'));
    }

    public function update(Request $r, InputGuru $observasi): JsonResponse
    {
        $r->validate([
            'siswa_kelas_id' => ['sometimes', 'integer', Rule::exists('siswa_kelass', 'id')],
            'teks' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'tanggal' => ['nullable', 'date'],
            'kondisi_siswa' => ['sometimes', Rule::in(['green', 'yellow', 'orange', 'red', 'black', 'grey'])],
            'gambar' => ['nullable', 'image', 'max:2048'],
            'kategori_ids' => ['array'],
            'kategori_ids.*' => [Rule::exists('kategori_masalahs', 'id')],
        ]);

        $data = $r->only(['siswa_kelas_id', 'tanggal', 'kondisi_siswa']);

        // If WK, ensure new siswa_kelas (if provided) belongs to own kelas
        $wkKelasId = \App\Models\Kelas::where('wali_guru_id', $r->user()->id)->latest('tahun_ajaran_id')->value('id');
        if ($wkKelasId && $r->filled('siswa_kelas_id')) {
            $sk = SiswaKelas::findOrFail((int)$r->siswa_kelas_id);
            if ($sk->kelas_id !== (int)$wkKelasId) {
                abort(403, 'Anda hanya bisa input untuk siswa di kelas Anda.');
            }
        }
        $teks = $r->input('teks');
        if (($teks === null || $teks === '') && $r->filled('catatan')) {
            $teks = $r->input('catatan');
        }
        if ($teks !== null) {
            $data['teks'] = $teks;
        }

        // duplicate guard on update: if target siswa_kelas/tanggal collide with another row
    $targetTanggal = $r->input('tanggal') ?: (string) $observasi->tanggal;
        $targetSiswaKelasId = $r->filled('siswa_kelas_id') ? (int)$r->siswa_kelas_id : (int)$observasi->siswa_kelas_id;
        $dup = InputGuru::where('guru_id', $observasi->guru_id)
            ->where('siswa_kelas_id', $targetSiswaKelasId)
            ->whereDate('tanggal', $targetTanggal)
            ->where('id', '<>', $observasi->id)
            ->first();
        if ($dup) {
            return response()->json([
                'message' => 'Observasi untuk siswa ini pada tanggal tersebut sudah ada. Ingin mengedit yang sudah ada?',
                'existing_id' => $dup->id,
            ], 409);
        }

        if ($r->hasFile('gambar')) {
            // delete old if exists
            if ($observasi->gambar) {
                Storage::disk('public')->delete($observasi->gambar);
            }
            $data['gambar'] = $r->file('gambar')->store('observasi', 'public');
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
