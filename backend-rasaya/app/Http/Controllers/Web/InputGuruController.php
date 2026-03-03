<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InputGuru;
use App\Models\SiswaKelas;
use App\Models\MasterKategoriMasalah;
use App\Services\GuestSandboxService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;

class InputGuruController extends Controller
{
    private function sandbox(): GuestSandboxService
    {
        return app(GuestSandboxService::class);
    }

    private function isGuestGuruBk(Request $request): bool
    {
        return $this->sandbox()->isGuestGuruBk($request);
    }

    private function buildGuestObservasiPayload(array $item): array
    {
        $siswaKelasId = (int) ($item['siswa_kelas_id'] ?? 0);
        $masterId = (int) ($item['master_kategori_masalah_id'] ?? 0);

        $siswaKelas = $siswaKelasId > 0
            ? SiswaKelas::with(['siswa.user', 'kelas.jurusan'])->find($siswaKelasId)
            : null;
        $masterKategori = $masterId > 0
            ? MasterKategoriMasalah::find($masterId)
            : null;

        return [
            'id' => (int) ($item['id'] ?? 0),
            'guru_id' => (int) ($item['guru_id'] ?? 0),
            'siswa_kelas_id' => $siswaKelasId,
            'master_kategori_masalah_id' => $masterId ?: null,
            'tanggal' => (string) ($item['tanggal'] ?? now()->toDateString()),
            'teks' => (string) ($item['teks'] ?? ''),
            'kondisi_siswa' => (string) ($item['kondisi_siswa'] ?? 'grey'),
            'gambar' => null,
            'gambar_url' => null,
            'created_at' => $item['created_at'] ?? now()->toISOString(),
            'updated_at' => $item['updated_at'] ?? now()->toISOString(),
            'siswa_kelas' => $siswaKelas,
            'master_kategori' => $masterKategori,
        ];
    }

    public function index(Request $r)
    {
        $guruId = data_get($r->user(), 'guru.user_id') ?? $r->user()->id;
        $filterMaster = (string) $r->input('filter_master_kategori_id', '');

        if ($this->isGuestGuruBk($r)) {
            $items = collect($this->sandbox()->getGuruBkObservasi($r));

            $siswaKelasMap = SiswaKelas::with(['siswa.user', 'kelas.jurusan'])
                ->whereIn('id', $items->pluck('siswa_kelas_id')->filter()->map(fn($id) => (int) $id)->unique()->values()->all())
                ->get()
                ->keyBy('id');

            $masterMap = MasterKategoriMasalah::whereIn('id', $items->pluck('master_kategori_masalah_id')->filter()->map(fn($id) => (int) $id)->unique()->values()->all())
                ->get()
                ->keyBy('id');

            $filtered = $items->filter(function (array $item) use ($r, $filterMaster, $siswaKelasMap) {
                $skId = (int) ($item['siswa_kelas_id'] ?? 0);
                $sk = $siswaKelasMap->get($skId);

                if ($r->filled('kelas_id')) {
                    $kelasId = (int) $r->input('kelas_id');
                    if ((int) data_get($sk, 'kelas_id', 0) !== $kelasId) {
                        return false;
                    }
                }

                if ($r->filled('kondisi') && (string) ($item['kondisi_siswa'] ?? '') !== (string) $r->input('kondisi')) {
                    return false;
                }

                $tanggal = (string) ($item['tanggal'] ?? '');
                if ($r->filled('date_from') && $tanggal < (string) $r->input('date_from')) {
                    return false;
                }
                if ($r->filled('date_to') && $tanggal > (string) $r->input('date_to')) {
                    return false;
                }

                if ($r->filled('q')) {
                    $query = mb_strtolower((string) $r->input('q'));
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        (string) ($item['teks'] ?? ''),
                        (string) data_get($sk, 'label', ''),
                        (string) data_get($sk, 'siswa.user.name', ''),
                        (string) data_get($sk, 'siswa.user.identifier', ''),
                    ])));
                    if (!str_contains($haystack, $query)) {
                        return false;
                    }
                }

                if ($filterMaster !== '' && (int) ($item['master_kategori_masalah_id'] ?? 0) !== (int) $filterMaster) {
                    return false;
                }

                return true;
            })->sortByDesc(fn(array $item) => (int) ($item['id'] ?? 0))->values();

            $page = max(1, (int) $r->input('page', 1));
            $perPage = 15;
            $total = $filtered->count();
            $slice = $filtered->slice(($page - 1) * $perPage, $perPage)->values();

            $models = $slice->map(function (array $item) use ($siswaKelasMap, $masterMap) {
                $model = new InputGuru();
                $model->forceFill([
                    'id' => (int) ($item['id'] ?? 0),
                    'guru_id' => (int) ($item['guru_id'] ?? 0),
                    'siswa_kelas_id' => (int) ($item['siswa_kelas_id'] ?? 0),
                    'master_kategori_masalah_id' => !empty($item['master_kategori_masalah_id']) ? (int) $item['master_kategori_masalah_id'] : null,
                    'tanggal' => (string) ($item['tanggal'] ?? now()->toDateString()),
                    'teks' => (string) ($item['teks'] ?? ''),
                    'kondisi_siswa' => (string) ($item['kondisi_siswa'] ?? 'grey'),
                    'gambar' => null,
                    'created_at' => $item['created_at'] ?? now(),
                    'updated_at' => $item['updated_at'] ?? now(),
                ]);
                $model->exists = true;

                $sk = $siswaKelasMap->get((int) ($item['siswa_kelas_id'] ?? 0));
                if ($sk) {
                    $model->setRelation('siswaKelas', $sk);
                }
                $mk = $masterMap->get((int) ($item['master_kategori_masalah_id'] ?? 0));
                if ($mk) {
                    $model->setRelation('masterKategori', $mk);
                }

                return $model;
            });

            $rows = new LengthAwarePaginator(
                $models,
                $total,
                $perPage,
                $page,
                [
                    'path' => route('guru.observasi.index'),
                    'query' => $r->query(),
                ]
            );
        } else {
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
                $text = '%' . (string) $r->input('q') . '%';
                $q->where('teks', 'like', $text); // khusus cari di catatan saja
            }
            if ($filterMaster !== '') {
                $q->where('master_kategori_masalah_id', (int) $filterMaster);
            }

            $rows = $q->paginate(15)->withQueryString();
        }

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
        $masterKategoris = MasterKategoriMasalah::aktif()->orderBy('nama')->get(['id', 'nama']);

        // Pass filters back to view
        $filters = [
            'q' => (string) $r->input('q', ''),
            'kelas_id' => (string) $r->input('kelas_id', ''),
            'kondisi' => (string) $r->input('kondisi', ''),
            'date_from' => (string) $r->input('date_from', ''),
            'date_to' => (string) $r->input('date_to', ''),
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
            'master_kategori_masalah_id' => ['nullable', 'integer', Rule::exists('master_kategori_masalahs', 'id')],
        ]);

        // ambil id guru dari relasi; fallback ke user->id (karena gurus.user_id == users.id)
        $guruId = data_get($r->user(), 'guru.user_id') ?? $r->user()->id;
        abort_if(!$guruId, 403, 'Guru tidak valid');

        // If this user is a wali kelas, restrict siswa_kelas selection to their own kelas
        $wkKelasId = \App\Models\Kelas::where('wali_guru_id', $r->user()->id)->latest('tahun_ajaran_id')->value('id');
        if ($wkKelasId) {
            $sk = SiswaKelas::findOrFail((int) $r->siswa_kelas_id);
            if ($sk->kelas_id !== (int) $wkKelasId) {
                abort(403, 'Anda hanya bisa input untuk siswa di kelas Anda.');
            }
        }

        $teks = $r->input('teks');
        if ($teks === null || $teks === '')
            $teks = $r->input('catatan', '');

        // tanggal target: selalu hari ini (kunci di server)
        $tanggal = now()->toDateString();

        if ($this->isGuestGuruBk($r)) {
            $items = $this->sandbox()->getGuruBkObservasi($r);

            $dup = collect($items)->first(function (array $row) use ($guruId, $r, $tanggal) {
                return (int) ($row['guru_id'] ?? 0) === (int) $guruId
                    && (int) ($row['siswa_kelas_id'] ?? 0) === (int) $r->siswa_kelas_id
                    && (string) ($row['tanggal'] ?? '') === $tanggal;
            });

            if ($dup) {
                return response()->json([
                    'message' => 'Anda sudah mengisi observasi untuk siswa ini pada hari ini. Ingin mengedit yang sudah ada?',
                    'existing_id' => (int) ($dup['id'] ?? 0),
                ], 409);
            }

            $row = [
                'id' => 900000 + $this->sandbox()->nextGuruBkObservasiId($r),
                'guru_id' => (int) $guruId,
                'siswa_kelas_id' => (int) $r->siswa_kelas_id,
                'master_kategori_masalah_id' => $r->filled('master_kategori_masalah_id') ? (int) $r->master_kategori_masalah_id : null,
                'tanggal' => $tanggal,
                'teks' => (string) $teks,
                'kondisi_siswa' => (string) $r->input('kondisi_siswa'),
                'gambar' => null,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];

            $items[] = $row;
            $this->sandbox()->putGuruBkObservasi($r, $items);

            return response()->json($this->buildGuestObservasiPayload($row), 201);
        }

        // duplicate guard: guru + siswa_kelas + tanggal
        $dup = InputGuru::where('guru_id', $guruId)
            ->where('siswa_kelas_id', (int) $r->siswa_kelas_id)
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


    public function show(Request $r, int $observasi): JsonResponse
    {
        if ($this->isGuestGuruBk($r)) {
            $item = collect($this->sandbox()->getGuruBkObservasi($r))
                ->first(fn(array $x) => (int) ($x['id'] ?? 0) === $observasi);

            abort_if(!$item, 404);
            return response()->json($this->buildGuestObservasiPayload($item));
        }

        $row = InputGuru::findOrFail($observasi);
        return response()->json($row->load('siswaKelas.siswa.user', 'siswaKelas.kelas.jurusan', 'masterKategori'));
    }

    public function update(Request $r, int $observasi): JsonResponse
    {
        $r->validate([
            'siswa_kelas_id' => ['sometimes', 'integer', Rule::exists('siswa_kelass', 'id')],
            'teks' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'kondisi_siswa' => ['sometimes', Rule::in(['green', 'yellow', 'orange', 'red', 'black', 'grey'])],
            'gambar' => ['nullable', 'image', 'max:2048'],
            'master_kategori_masalah_id' => ['sometimes', 'nullable', 'integer', Rule::exists('master_kategori_masalahs', 'id')],
        ]);

        if ($this->isGuestGuruBk($r)) {
            $items = $this->sandbox()->getGuruBkObservasi($r);
            $idx = collect($items)->search(fn(array $x) => (int) ($x['id'] ?? 0) === $observasi);
            abort_if($idx === false, 404);

            $current = $items[$idx];
            $targetSiswaKelasId = $r->filled('siswa_kelas_id') ? (int) $r->siswa_kelas_id : (int) ($current['siswa_kelas_id'] ?? 0);
            $targetTanggal = (string) ($current['tanggal'] ?? now()->toDateString());
            $guruId = (int) ($current['guru_id'] ?? (data_get($r->user(), 'guru.user_id') ?? $r->user()->id));

            $dup = collect($items)->first(function (array $row) use ($guruId, $targetSiswaKelasId, $targetTanggal, $observasi) {
                return (int) ($row['id'] ?? 0) !== $observasi
                    && (int) ($row['guru_id'] ?? 0) === $guruId
                    && (int) ($row['siswa_kelas_id'] ?? 0) === $targetSiswaKelasId
                    && (string) ($row['tanggal'] ?? '') === $targetTanggal;
            });

            if ($dup) {
                return response()->json([
                    'message' => 'Observasi untuk siswa ini pada tanggal tersebut sudah ada.',
                    'existing_id' => (int) ($dup['id'] ?? 0),
                ], 409);
            }

            $teks = $r->input('teks');
            if (($teks === null || $teks === '') && $r->filled('catatan')) {
                $teks = $r->input('catatan');
            }

            if ($r->filled('siswa_kelas_id')) {
                $current['siswa_kelas_id'] = (int) $r->siswa_kelas_id;
            }
            if ($r->filled('kondisi_siswa')) {
                $current['kondisi_siswa'] = (string) $r->input('kondisi_siswa');
            }
            if ($teks !== null) {
                $current['teks'] = (string) $teks;
            }
            if ($r->has('master_kategori_masalah_id')) {
                $current['master_kategori_masalah_id'] = $r->filled('master_kategori_masalah_id')
                    ? (int) $r->master_kategori_masalah_id
                    : null;
            }
            $current['updated_at'] = now()->toISOString();

            $items[$idx] = $current;
            $this->sandbox()->putGuruBkObservasi($r, $items);

            return response()->json($this->buildGuestObservasiPayload($current));
        }

        $observasi = InputGuru::findOrFail($observasi);
        $data = $r->only(['siswa_kelas_id', 'kondisi_siswa', 'master_kategori_masalah_id']);

        // ... (Validasi Wali Kelas & Teks sama seperti sebelumnya) ...
        $wkKelasId = \App\Models\Kelas::where('wali_guru_id', $r->user()->id)->latest('tahun_ajaran_id')->value('id');
        if ($wkKelasId && $r->filled('siswa_kelas_id')) {
            $sk = SiswaKelas::findOrFail((int) $r->siswa_kelas_id);
            if ($sk->kelas_id !== (int) $wkKelasId) {
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

        // ... (Validasi Duplikat sama seperti sebelumnya) ...
        $targetTanggal = (string) $observasi->tanggal;
        $targetSiswaKelasId = $r->filled('siswa_kelas_id') ? (int) $r->siswa_kelas_id : (int) $observasi->siswa_kelas_id;
        $dup = InputGuru::where('guru_id', $observasi->guru_id)
            ->where('siswa_kelas_id', $targetSiswaKelasId)
            ->whereDate('tanggal', $targetTanggal)
            ->where('id', '<>', $observasi->id)
            ->first();

        if ($dup) {
            return response()->json([
                'message' => 'Observasi untuk siswa ini pada tanggal tersebut sudah ada.',
                'existing_id' => $dup->id,
            ], 409);
        }

        // === BAGIAN INI YANG DIPERBAIKI ===

        // 1. Jika ada upload gambar BARU
        if ($r->hasFile('gambar')) {
            // Hapus gambar lama fisik
            if ($observasi->gambar) {
                Storage::disk('public')->delete($observasi->gambar);
            }
            // Simpan gambar baru
            $data['gambar'] = $r->file('gambar')->store('observasi', 'public');
        }
        // 2. Jika tidak upload baru, tapi bendera HAPUS dikirim (hapus_gambar == '1')
        elseif ($r->input('hapus_gambar') == '1') {
            // Hapus gambar lama fisik
            if ($observasi->gambar) {
                Storage::disk('public')->delete($observasi->gambar);
            }
            // Set di database jadi NULL
            $data['gambar'] = null;
        }

        // === SELESAI PERBAIKAN ===

        $observasi->update($data);

        return response()->json($observasi->load('siswaKelas.siswa', 'siswaKelas.kelas', 'masterKategori'));
    }

    public function destroy(Request $r, int $observasi)
    {
        if ($this->isGuestGuruBk($r)) {
            $items = array_values(array_filter(
                $this->sandbox()->getGuruBkObservasi($r),
                fn(array $x) => (int) ($x['id'] ?? 0) !== $observasi
            ));
            $this->sandbox()->putGuruBkObservasi($r, $items);
            return response()->noContent();
        }

        $observasi = InputGuru::findOrFail($observasi);
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
