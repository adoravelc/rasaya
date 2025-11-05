<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\KategoriMasalah;
use Illuminate\Validation\Rule;

class KategoriWebController extends Controller
{
    public function index(Request $r)
    {
        $q = KategoriMasalah::orderBy('nama');

        // filter ?aktif=1 / ?aktif=0 / (kosong = semua)
        if ($r->filled('aktif')) {
            $q->where('is_active', (int) $r->input('aktif') === 1);
        }

        $rows = $q->paginate(15)->withQueryString();
        $trashed = KategoriMasalah::onlyTrashed()->orderBy('nama')->get();

        return view('roles.admin.kategori.index', compact('rows', 'trashed'));
    }

    public function toggleActive(Request $r, KategoriMasalah $kategori)
    {
        $data = $r->validate(['is_active' => ['required', 'boolean']]);
        $kategori->is_active = (bool) $data['is_active'];
        $kategori->save();

        return ['ok' => true, 'data' => $kategori];
    }

    // AJAX create
    public function store(Request $r)
    {
        $data = $r->validate([
            'nama' => ['required', 'max:100'],
            'deskripsi' => ['nullable', 'max:255'],
            'is_active' => ['boolean'],
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
        return response()->json(['ok' => true, 'data' => $row], 201);
    }


    // AJAX update
    public function update(Request $r, KategoriMasalah $kategori)
    {
        // Kode bersifat otomatis dan tidak bisa diedit dari UI,
        // jadi kita tidak memvalidasi / memperbolehkan perubahan 'kode' di sini.
        $data = $r->validate([
            'nama' => ['required', 'max:100'],
            'deskripsi' => ['nullable', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        // Pastikan field 'kode' yang mungkin ikut terkirim diabaikan
        unset($data['kode']);

        $kategori->update($data);
        return ['ok' => true, 'data' => $kategori->fresh()];
    }

    // SOFT DELETE
    public function destroy(KategoriMasalah $kategori)
    {
        $kategori->delete();             // <- soft delete
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
        return ['ok' => true, 'data' => $row];
    }

    public function forceDelete($id)
    {
        $row = KategoriMasalah::onlyTrashed()->findOrFail($id);
        $row->forceDelete();
        return response()->noContent();
    }
}