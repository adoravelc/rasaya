<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TahunAjaran;

class TahunAjaranWebController extends Controller
{
    public function index(Request $r)
    {
        $q = TahunAjaran::query();
        if ($r->filled('is_active')) {
            $q->where('is_active', (bool) $r->boolean('is_active'));
        }
        $rows = $q->orderBy('nama', 'desc')->get();
        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'nama' => ['required','string','max:50','unique:tahun_ajarans,nama,NULL,id,deleted_at,NULL'],
            'mulai' => ['nullable','date'],
            'selesai' => ['nullable','date'],
            'is_active' => ['boolean'],
        ]);

        // Optionally, if set active, you may want to deactivate others
        // if (!empty($data['is_active'])) {
        //     TahunAjaran::query()->update(['is_active' => false]);
        // }

        $row = TahunAjaran::create($data + ['is_active' => (bool)($data['is_active'] ?? false)]);
        return response()->json(['ok' => true, 'data' => $row], 201);
    }
    public function toggleActive(Request $r, TahunAjaran $tahunAjaran)
    {
        $data = $r->validate(['is_active' => ['required','boolean']]);

        // Optional rule: allow multiple actives or enforce single active
        // For now, we allow multiple active; if need single active, uncomment below
        // if ($data['is_active']) {
        //     TahunAjaran::where('id', '<>', $tahunAjaran->id)->update(['is_active' => false]);
        // }

        $tahunAjaran->is_active = (bool)$data['is_active'];
        $tahunAjaran->save();

        return ['ok' => true, 'data' => $tahunAjaran];
    }

    public function destroy(TahunAjaran $tahunAjaran)
    {
        $tahunAjaran->delete();
        return response()->json(['ok' => true]);
    }

    public function trashed()
    {
        $rows = TahunAjaran::onlyTrashed()->orderBy('nama', 'desc')->get();
        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function restore($id)
    {
        $row = TahunAjaran::onlyTrashed()->findOrFail($id);
        $row->restore();
        return response()->json(['ok' => true]);
    }

    public function forceDelete($id)
    {
        $row = TahunAjaran::onlyTrashed()->findOrFail($id);
        $row->forceDelete();
        return response()->json(['ok' => true]);
    }
}
