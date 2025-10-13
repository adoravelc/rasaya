<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\KategoriMasalah;
use App\Http\Requests\StoreKategoriRequest;
use App\Http\Requests\UpdateKategoriRequest;

class KategoriMasalahController extends Controller
{
    public function index(Request $r)
    {
        $q = KategoriMasalah::query()->orderBy('nama');

        if ($r->boolean('only_trashed')) {
            $q->onlyTrashed();
        } elseif ($r->boolean('with_trashed')) {
            $q->withTrashed();
        }

        // kalau kamu masih mau filter aktif/tidak (kolom is_active)
        if ($r->filled('aktif')) {
            $q->where('is_active', (bool) $r->boolean('aktif'));
        }

        return $q->get();
    }

    public function store(StoreKategoriRequest $r)
    {
        $row = KategoriMasalah::create($r->validated());
        return response()->json($row, 201);
    }

    public function show(KategoriMasalah $kategoriMasalah)
    {
        return $kategoriMasalah;
    }

    public function update(UpdateKategoriRequest $r, KategoriMasalah $kategoriMasalah)
    {
        $kategoriMasalah->update($r->validated());
        return $kategoriMasalah;
    }

    // SOFT DELETE (default Eloquent)
    public function destroy(KategoriMasalah $kategoriMasalah)
    {
        $kategoriMasalah->delete(); // <- soft delete
        return response()->noContent();
    }

    // ===== tambahan soft-delete helpers =====

    // list khusus yang terhapus
    public function trashed()
    {
        return KategoriMasalah::onlyTrashed()->orderBy('nama')->get();
    }

    // restore dari soft delete
    public function restore($id)
    {
        $row = KategoriMasalah::onlyTrashed()->findOrFail($id);
        $row->restore();
        return response()->json(['ok' => true, 'data' => $row]);
    }

    // hapus permanen
    public function forceDelete($id)
    {
        $row = KategoriMasalah::onlyTrashed()->findOrFail($id);
        $row->forceDelete();
        return response()->noContent();
    }
}
