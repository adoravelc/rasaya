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
            'kode' => [
                'required',
                'max:10',
                'alpha_num',
                Rule::unique('kategori_masalahs', 'kode')->whereNull('deleted_at'),
            ],
            'nama' => ['required', 'max:100'],
            'deskripsi' => ['nullable', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $row = KategoriMasalah::create($data);
        return response()->json(['ok' => true, 'data' => $row], 201);
    }

    // AJAX update
    public function update(Request $r, KategoriMasalah $kategori)
    {
        $data = $r->validate([
            'kode' => [
                'required',
                'max:10',
                'alpha_num',
                Rule::unique('kategori_masalahs', 'kode')
                    ->ignore($kategori->id)
                    ->whereNull('deleted_at'),
            ],
            'nama' => ['required', 'max:100'],
            'deskripsi' => ['nullable', 'max:255'],
            'is_active' => ['boolean'],
        ]);

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
