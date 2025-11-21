<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InputGuru;
use App\Models\SiswaKelas;
use App\Models\KategoriMasalah;
use App\Models\MasterKategoriMasalah;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class InputGuruController extends Controller
{
    public function index(Request $r)
    {
        $guruId = data_get($r->user(), 'guru.user_id') ?? $r->user()->id;

        $q = InputGuru::with(['siswaKelas.siswa.user', 'siswaKelas.kelas.jurusan', 'masterKategori'])
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
        // optional filter by topik besar (single)
        $filterMaster = (string) $r->input('filter_master_kategori_id', '');
        if ($filterMaster !== '') {
            $q->where('master_kategori_masalah_id', (int) $filterMaster);
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
        $siswaKelasRaw = $siswaKelasQ
            ->orderBy('kelas_id')
            ->get();

        // Ambil daftar siswa_kelas yang sedang butuh perhatian dari analisis
        $flaggedQuery = \App\Models\AnalisisEntry::where('needs_attention', true);
        if ($wkKelasId) {
            $flaggedQuery->whereHas('siswaKelas', fn($qq) => $qq->where('kelas_id', $wkKelasId));
        }
        $flaggedIds = $flaggedQuery->pluck('siswa_kelas_id')->unique()->filter()->values()->all();

        // Reorder: flagged muncul dulu
        $siswaKelas = $siswaKelasRaw
            ->sortBy(fn($row) => in_array($row->id, $flaggedIds) ? 0 : 1)
            ->values()
            ->map(fn($sk) => ['id' => $sk->id, 'label' => $sk->label]);

    $opsiKondisi = ['green', 'yellow', 'orange', 'red', 'black', 'grey'];
    // Topik besar (master) untuk pilihan guru (hanya satu)
    $masterKategoris = MasterKategoriMasalah::aktif()->orderBy('nama')->get(['id','nama']);

        // Pass filters back to view
        $filters = [
            'q' => (string) $r->input('q',''),
            'kelas_id' => (string) $r->input('kelas_id',''),
            'kondisi' => (string) $r->input('kondisi',''),
            'date_from' => (string) $r->input('date_from',''),
            'date_to' => (string) $r->input('date_to',''),
            'filter_master_kategori_id' => $filterMaster,
        ];

    return view('roles.guru.observasi.index', compact('rows', 'siswaKelas', 'masterKategoris', 'opsiKondisi', 'filters', 'kelasOptions', 'wkKelasId', 'flaggedIds'));
    }

    public function store(Request $r): JsonResponse
    {
        $r->validate([
            'siswa_kelas_id' => ['required', 'integer', Rule::exists('siswa_kelass', 'id')],
            'teks' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            // tanggal ditetapkan otomatis (hari ini), abaikan input dari klien
            'kondisi_siswa' => ['required', Rule::in(['green', 'yellow', 'orange', 'red', 'black', 'grey'])],
            'gambar' => ['nullable', 'image', 'max:2048'],
            'master_kategori_masalah_id' => ['nullable','integer', Rule::exists('master_kategori_masalahs','id')],
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

    // tanggal target: selalu hari ini (kunci di server)
    $tanggal = now()->toDateString();

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
            'master_kategori_masalah_id' => $r->integer('master_kategori_masalah_id'),
            'tanggal' => $tanggal,
            'teks' => $teks,
            'gambar' => $gambarPath,
            'kondisi_siswa' => $r->input('kondisi_siswa'),
        ]);

        // sub-kategori tidak lagi dipilih manual; akan diisi otomatis via analisis di langkah terpisah

        return response()->json($row->load('siswaKelas.siswa', 'siswaKelas.kelas', 'masterKategori'), 201);
    }


    public function show(InputGuru $observasi): JsonResponse
    {
    return response()->json($observasi->load('siswaKelas.siswa.user', 'siswaKelas.kelas.jurusan', 'masterKategori'));
    }

    public function update(Request $r, InputGuru $observasi): JsonResponse
    {
        $r->validate([
            'siswa_kelas_id' => ['sometimes', 'integer', Rule::exists('siswa_kelass', 'id')],
            'teks' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            // tanggal tidak dapat diubah melalui update
            'kondisi_siswa' => ['sometimes', Rule::in(['green', 'yellow', 'orange', 'red', 'black', 'grey'])],
            'gambar' => ['nullable', 'image', 'max:2048'],
            'master_kategori_masalah_id' => ['sometimes','nullable','integer', Rule::exists('master_kategori_masalahs','id')],
        ]);

        // Jangan pernah menerima perubahan tanggal via update
    $data = $r->only(['siswa_kelas_id', 'kondisi_siswa','master_kategori_masalah_id']);

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

        // duplicate guard on update: tanggal dikunci ke tanggal observasi
        $targetTanggal = (string) $observasi->tanggal;
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

        // tidak lagi menerima kategori_ids manual

        return response()->json($observasi->load('siswaKelas.siswa', 'siswaKelas.kelas', 'masterKategori'));
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
