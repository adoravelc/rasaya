<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\KategoriMasalah;
use App\Models\MasterRekomendasi;
use Illuminate\Validation\Rule;

class RekomendasiWebController extends Controller
{
    public function index(Request $r)
    {
        $kategoris = KategoriMasalah::orderBy('nama')->get();
        $kategoriId = $r->integer('kategori_id');

        $selectedKategori = null;
        $q = MasterRekomendasi::with('kategoris')->orderBy('kode');
        if ($kategoriId) {
            $selectedKategori = $kategoris->firstWhere('id', $kategoriId);
            $q->whereHas('kategoris', function ($qq) use ($kategoriId) {
                $qq->where('kategori_masalah_id', $kategoriId);
            });
        }
        $rows = $q->paginate(15)->withQueryString();

        return view('roles.admin.rekomendasi.index', compact('kategoris', 'selectedKategori', 'rows'));
    }

    public function suggestKode(Request $r)
    {
    $kategoriId = $r->input('kategori_id') ?? $r->integer('kategori_id');
    $kategori = KategoriMasalah::findOrFail((int) $kategoriId);
        $prefix = strtoupper(trim($kategori->kode));
        $latest = MasterRekomendasi::where('kode', 'like', $prefix . '\_%')
            ->orderBy('kode', 'desc')
            ->value('kode');
        $nextNum = 1;
        if ($latest) {
            $parts = explode('_', $latest);
            $last = end($parts);
            if (ctype_digit($last)) $nextNum = (int) $last + 1;
        }
        $kode = sprintf('%s_%02d', $prefix, $nextNum);
        return ['ok' => true, 'kode' => $kode];
    }

    public function store(Request $r)
    {
        $kategori = KategoriMasalah::findOrFail($r->integer('kategori_id'));

        $data = $r->validate([
            'kode' => ['nullable', 'string', 'max:100', Rule::unique('master_rekomendasis', 'kode')],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['required', 'string'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high'])],
            'is_active' => ['boolean'],
            'min_neg_score' => ['nullable', 'numeric'],
            'any_keywords' => ['nullable', 'string'],
        ]);

        // Generate kode if empty
        if (empty($data['kode'])) {
            $suggest = $this->suggestKode(new Request(['kategori_id' => $kategori->id]));
            $data['kode'] = $suggest['kode'];
        }

        $rules = [];
        if (isset($data['min_neg_score']) && $data['min_neg_score'] !== null) {
            $rules['min_neg_score'] = (float) $data['min_neg_score'];
        }
        if (!empty($data['any_keywords'])) {
            $rules['any_keywords'] = collect(explode(',', $data['any_keywords']))
                ->map(fn($s) => trim($s))
                ->filter()
                ->values()
                ->all();
        }

        $row = new MasterRekomendasi();
        $row->kode = $data['kode'];
        $row->judul = $data['judul'];
        $row->deskripsi = $data['deskripsi'] ?? null;
        $row->severity = $data['severity'];
        $row->is_active = (bool) ($data['is_active'] ?? true);
        $row->rules = $rules ?: null;
        $row->save();

        $row->kategoris()->syncWithoutDetaching([$kategori->id]);

        return response()->json(['ok' => true, 'data' => $row], 201);
    }

    public function update(Request $r, MasterRekomendasi $rekomendasi)
    {
        $data = $r->validate([
            'kode' => ['required', 'string', 'max:100', Rule::unique('master_rekomendasis', 'kode')->ignore($rekomendasi->id)],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['required', 'string'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high'])],
            'is_active' => ['boolean'],
            'min_neg_score' => ['nullable', 'numeric'],
            'any_keywords' => ['nullable', 'string'],
        ]);

        $rules = [];
        if (isset($data['min_neg_score']) && $data['min_neg_score'] !== null) {
            $rules['min_neg_score'] = (float) $data['min_neg_score'];
        }
        if (!empty($data['any_keywords'])) {
            $rules['any_keywords'] = collect(explode(',', $data['any_keywords']))
                ->map(fn($s) => trim($s))
                ->filter()
                ->values()
                ->all();
        }

        $rekomendasi->update([
            'kode' => $data['kode'],
            'judul' => $data['judul'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'severity' => $data['severity'],
            'is_active' => (bool) ($data['is_active'] ?? $rekomendasi->is_active),
            'rules' => $rules ?: null,
        ]);

        return ['ok' => true, 'data' => $rekomendasi->fresh()];
    }

    public function toggleActive(Request $r, MasterRekomendasi $rekomendasi)
    {
        $data = $r->validate(['is_active' => ['required', 'boolean']]);
        $rekomendasi->is_active = (bool) $data['is_active'];
        $rekomendasi->save();
        return ['ok' => true, 'data' => $rekomendasi];
    }

    public function destroy(Request $r, MasterRekomendasi $rekomendasi)
    {
        $kategoriId = $r->integer('kategori_id');
        if ($kategoriId) {
            $rekomendasi->kategoris()->detach($kategoriId);
            if ($rekomendasi->kategoris()->count() === 0) {
                $rekomendasi->delete();
                return response()->json(['ok' => true, 'deleted' => true]);
            }
            return ['ok' => true, 'detached' => true];
        }
        $rekomendasi->delete();
        return ['ok' => true];
    }
}
