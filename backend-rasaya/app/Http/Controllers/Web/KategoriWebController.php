<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\KategoriMasalah;
use App\Models\MasterKategoriMasalah;
use Illuminate\Validation\Rule;
use App\Services\TaxonomySync;

class KategoriWebController extends Controller
{
    public function index(Request $r)
    {
        $q = KategoriMasalah::with('topikBesars')->orderBy('nama');

        // filter ?aktif=1 / ?aktif=0 / (kosong = semua)
        if ($r->filled('aktif')) {
            $q->where('is_active', (int) $r->input('aktif') === 1);
        }

        // filter topik besar (master) => tampilkan hanya small topics yang terhubung ke master terpilih
        $masterId = $r->input('master_id');
        if ($masterId) {
            $q->whereHas('topikBesars', function($qq) use ($masterId) {
                $qq->where('master_kategori_masalah_id', $masterId);
            });
        }

        // search by kode/nama
        if ($r->filled('q')) {
            $term = trim($r->input('q'));
            $q->where(function($qq) use ($term){
                $qq->where('nama','like',"%{$term}%")
                   ->orWhere('kode','like',"%{$term}%");
            });
        }

        $rows = $q->paginate(15)->withQueryString();
        $trashed = KategoriMasalah::onlyTrashed()->orderBy('nama')->get();
        $masters = MasterKategoriMasalah::orderBy('nama')->get(['id','nama','kode']);

        $qTerm = $r->input('q');
        return view('roles.admin.kategori.index', compact('rows', 'trashed', 'masters', 'masterId', 'qTerm'));
    }

    public function detail(KategoriMasalah $kategori)
    {
        $kategori->load(['topikBesars']);
        $rekoms = \App\Models\MasterRekomendasi::whereHas('kategoris', function($q) use ($kategori){
            $q->where('kategori_masalah_id', $kategori->id);
        })->orderBy('kode')->get(['id','kode','judul','severity','is_active','rules']);

        return [
            'ok' => true,
            'kategori' => [
                'id' => $kategori->id,
                'kode' => $kategori->kode,
                'nama' => $kategori->nama,
                'deskripsi' => $kategori->deskripsi,
                'kata_kunci' => is_array($kategori->kata_kunci) ? $kategori->kata_kunci : (empty($kategori->kata_kunci) ? [] : (array) $kategori->kata_kunci),
                'topik_besar' => $kategori->topikBesars->map(fn($m)=>[
                    'id'=>$m->id,
                    'kode'=>$m->kode,
                    'nama'=>$m->nama,
                    'deskripsi'=>$m->deskripsi,
                ])->values(),
            ],
            'rekomendasis' => $rekoms,
        ];
    }

    public function toggleActive(Request $r, KategoriMasalah $kategori)
    {
        $data = $r->validate(['is_active' => ['required', 'boolean']]);
        $kategori->is_active = (bool) $data['is_active'];
        $kategori->save();

        // Sync to taxonomy.json so ML immediately stops/starts using this category
        try {
            $sync = new TaxonomySync();
            $sync->syncAll();
        } catch (\Throwable $e) {
            Log::warning('Failed to sync taxonomy after toggle active', ['error' => $e->getMessage()]);
        }

        return ['ok' => true, 'data' => $kategori];
    }

    // AJAX create
    public function store(Request $r)
    {
        $data = $r->validate([
            'nama' => ['required', 'max:100'],
            'deskripsi' => ['nullable', 'max:255'],
            'kata_kunci' => ['nullable', 'array'],
            'kata_kunci.*' => ['string', 'max:50'],
            'is_active' => ['boolean'],
            'master_id' => ['nullable','integer'],
        ]);

        // === Generate kode otomatis ===
        $nama = strtoupper($data['nama']);
        $words = preg_split('/\s+/', $nama);

        // Ambil huruf pertama dari setiap kata (maksimal 4 huruf)
        $kode = '';
        foreach ($words as $w) {
            $kode .= substr($w, 0, 1);
            if (strlen($kode) >= 4)
                break;
        }

        // Kalau hasil kurang dari 4 huruf, isi dengan huruf dari kata pertama
        if (strlen($kode) < 4) {
            $kode .= substr(preg_replace('/\s+/', '', $nama), 0, 4 - strlen($kode));
        }

        // Pastikan kode unik
        $originalKode = $kode;
        $i = 2;
        while (KategoriMasalah::withTrashed()->where('kode', $kode)->exists()) {
            $kode = $originalKode . $i;
            $i++;
        }

        $data['kode'] = $kode;

        // Simpan data
        $row = KategoriMasalah::create($data);
        // Attach to selected big category if provided
        if (!empty($data['master_id'])) {
            try {
                $row->topikBesars()->syncWithoutDetaching([$data['master_id']]);
            } catch (\Throwable $e) {}
        }
        
        // Sync to taxonomy.json
        try {
            $sync = new TaxonomySync();
            $sync->syncAll();
        } catch (\Throwable $e) {
            Log::warning('Failed to sync taxonomy after create', ['error' => $e->getMessage()]);
        }
        
        return response()->json(['ok' => true, 'data' => $row->load('topikBesars')], 201);
    }


    // AJAX update
    public function update(Request $r, KategoriMasalah $kategori)
    {
        // Kode bersifat otomatis dan tidak bisa diedit dari UI,
        // jadi kita tidak memvalidasi / memperbolehkan perubahan 'kode' di sini.
        $data = $r->validate([
            'nama' => ['required', 'max:100'],
            'deskripsi' => ['nullable', 'max:255'],
            'kata_kunci' => ['nullable', 'array'],
            'kata_kunci.*' => ['string', 'max:50'],
            'is_active' => ['boolean'],
            'master_id' => ['nullable','integer'],
        ]);

        // Pastikan field 'kode' yang mungkin ikut terkirim diabaikan
        unset($data['kode']);

        $kategori->update($data);
        
        // Update master relationship if provided
        if (isset($data['master_id'])) {
            $kategori->topikBesars()->sync([$data['master_id']]);
        }
        
        // Sync to taxonomy.json
        try {
            $sync = new TaxonomySync();
            $sync->syncAll();
        } catch (\Throwable $e) {
            Log::warning('Failed to sync taxonomy after update', ['error' => $e->getMessage()]);
        }
        
        return ['ok' => true, 'data' => $kategori->fresh('topikBesars')];
    }

    // Create Master (Kategori Besar)
    public function storeMaster(Request $r)
    {
        $data = $r->validate([
            'nama' => ['required','max:100'],
            'deskripsi' => ['nullable','max:255'],
            'is_active' => ['boolean'],
        ]);

        // Generate kode otomatis berdasar nama (mirip small)
        $nama = strtoupper($data['nama']);
        $words = preg_split('/\s+/', $nama);
        $kode = '';
        foreach ($words as $w) {
            $kode .= substr($w, 0, 1);
            if (strlen($kode) >= 4) break;
        }
        if (strlen($kode) < 4) {
            $kode .= substr(preg_replace('/\s+/', '', $nama), 0, 4 - strlen($kode));
        }
        $originalKode = $kode;
        $i = 2;
        while (\App\Models\MasterKategoriMasalah::withTrashed()->where('kode', $kode)->exists()) {
            $kode = $originalKode . $i;
            $i++;
        }

        $row = new \App\Models\MasterKategoriMasalah();
        $row->kode = $kode;
        $row->nama = $data['nama'];
        $row->deskripsi = $data['deskripsi'] ?? null;
        $row->is_active = (bool) ($data['is_active'] ?? true);
        $row->save();

        // Sync to taxonomy.json
        try {
            $sync = new TaxonomySync();
            $sync->syncAll();
        } catch (\Throwable $e) {
            Log::warning('Failed to sync taxonomy after create master', ['error' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'data' => $row], 201);
    }

    // Update Master (Kategori Besar)
    public function updateMaster(Request $r, $id)
    {
        $master = MasterKategoriMasalah::findOrFail($id);
        $data = $r->validate([
            'nama' => ['required','max:100'],
            'deskripsi' => ['nullable','max:255'],
            'is_active' => ['boolean'],
        ]);

        $master->nama = $data['nama'];
        $master->deskripsi = $data['deskripsi'] ?? null;
        $master->is_active = (bool) ($data['is_active'] ?? $master->is_active);
        $master->save();

        // Sync to taxonomy.json
        try {
            $sync = new TaxonomySync();
            $sync->syncAll();
        } catch (\Throwable $e) {
            Log::warning('Failed to sync taxonomy after update master', ['error' => $e->getMessage()]);
        }

        return ['ok' => true, 'data' => $master];
    }

    // Toggle Active Master (Kategori Besar)
    public function toggleActiveMaster(Request $r, $id)
    {
        $master = MasterKategoriMasalah::findOrFail($id);
        $data = $r->validate(['is_active' => ['required', 'boolean']]);
        $master->is_active = (bool) $data['is_active'];
        $master->save();

        // Sync to taxonomy.json so ML immediately stops/starts using this master bucket
        try {
            $sync = new TaxonomySync();
            $sync->syncAll();
        } catch (\Throwable $e) {
            Log::warning('Failed to sync taxonomy after toggle active master', ['error' => $e->getMessage()]);
        }

        return ['ok' => true, 'data' => $master];
    }

    // Soft Delete Master (Kategori Besar)
    public function destroyMaster($id)
    {
        $master = MasterKategoriMasalah::findOrFail($id);
        $master->delete();
        
        // Sync to taxonomy.json
        try {
            $sync = new TaxonomySync();
            $sync->syncAll();
        } catch (\Throwable $e) {
            Log::warning('Failed to sync taxonomy after delete master', ['error' => $e->getMessage()]);
        }
        
        return ['ok' => true];
    }

    // SOFT DELETE
    public function destroy(KategoriMasalah $kategori)
    {
        $kategori->delete();             // <- soft delete
        
        // Sync to taxonomy.json
        try {
            $sync = new TaxonomySync();
            $sync->syncAll();
        } catch (\Throwable $e) {
            Log::warning('Failed to sync taxonomy after delete', ['error' => $e->getMessage()]);
        }
        
        return ['ok' => true];
    }

    // ===== helper endpoints untuk soft-deletes =====

    // (opsional) list khusus terhapus bila ingin dipanggil via AJAX
    public function trashed()
    {
        return KategoriMasalah::onlyTrashed()->orderBy('nama')->get();
    }

    public function restore($id)
    {
        $row = KategoriMasalah::onlyTrashed()->findOrFail($id);
        $row->restore();
        
        // Sync to taxonomy.json
        try {
            $sync = new TaxonomySync();
            $sync->syncAll();
        } catch (\Throwable $e) {
            Log::warning('Failed to sync taxonomy after restore', ['error' => $e->getMessage()]);
        }
        
        return ['ok' => true, 'data' => $row];
    }

    public function forceDelete($id)
    {
        $row = KategoriMasalah::onlyTrashed()->findOrFail($id);
        $row->forceDelete();
        
        // Sync to taxonomy.json
        try {
            $sync = new TaxonomySync();
            $sync->syncAll();
        } catch (\Throwable $e) {
            Log::warning('Failed to sync taxonomy after force delete', ['error' => $e->getMessage()]);
        }
        
        return response()->noContent();
    }
}