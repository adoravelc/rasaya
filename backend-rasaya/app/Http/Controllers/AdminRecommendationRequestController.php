<?php

namespace App\Http\Controllers;

use App\Models\RekomendasiRequest;
use App\Models\MasterRekomendasi;
use App\Models\KategoriMasalah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminRecommendationRequestController extends Controller
{
    public function index()
    {
        $requests = RekomendasiRequest::with(['kategori', 'requester'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return view('roles.admin.rekomendasi.requests.index', compact('requests'));
    }

    public function admit(int $id)
    {
        $req = RekomendasiRequest::findOrFail($id);
        if ($req->status === 'approved') {
            return back()->with('info', 'Permintaan sudah disetujui.');
        }

        // Generate kode otomatis berbasis kategori kecil (mirip RekomendasiWebController::suggestKode)
        $kode = $this->generateKodeForKategori($req->kategori_masalah_id);

        // Create MasterRekomendasi entry
        $mr = MasterRekomendasi::create([
            'kode' => $kode,
            'judul' => $req->judul,
            'deskripsi' => $req->deskripsi,
            'severity' => $req->severity ?: 'low',
            'is_active' => true,
            'rules' => $req->rules ?? [],
        ]);
        // attach kategori via pivot
        if ($req->kategori_masalah_id) {
            $mr->kategoris()->attach($req->kategori_masalah_id);
        }
        // Kolom approved_at belum ada di tabel, jadi hanya update status saja
        $req->update(['status' => 'approved']);
        return back()->with('success', 'Rekomendasi ditambahkan dan permintaan disetujui.');
    }

    public function reject(int $id)
    {
        $req = RekomendasiRequest::findOrFail($id);
        // Kolom rejected_at belum ada di tabel, jadi hanya update status saja
        $req->update(['status' => 'rejected']);
        return back()->with('warning', 'Permintaan ditolak.');
    }

    public function edit(int $id)
    {
        $req = RekomendasiRequest::with('kategori')->findOrFail($id);
        return view('roles.admin.rekomendasi.requests.edit', compact('req'));
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'judul' => 'nullable|string',
            'deskripsi' => 'required|string',
            'severity' => 'nullable|in:low,medium,high',
            'min_neg_score' => 'nullable|numeric|min:-1|max:0',
        ]);
        $req = RekomendasiRequest::findOrFail($id);

        // Build rules baru: ambil dari request awal, lalu override min_neg_score kalau diisi
        $rules = $req->rules ?? [];
        if (!is_array($rules)) {
            $rules = [];
        }
        if ($request->filled('min_neg_score')) {
            $rules['min_neg_score'] = (float) $request->input('min_neg_score');
        }

        // Generate kode otomatis berbasis kategori kecil (atau fallback umum)
        $kode = $this->generateKodeForKategori($req->kategori_masalah_id);

        // Save to MasterRekomendasi (edited version)
        $mr = MasterRekomendasi::create([
            'kode' => $kode,
            'judul' => $request->input('judul'),
            'deskripsi' => $request->input('deskripsi'),
            'severity' => $request->input('severity') ?: 'low',
            'is_active' => true,
            'rules' => $rules,
        ]);
        if ($req->kategori_masalah_id) {
            $mr->kategoris()->attach($req->kategori_masalah_id);
        }
        // Sama seperti admit(): hanya update status karena kolom approved_at belum ada
        $req->update(['status' => 'approved']);
        return redirect()->route('admin.rekomendasi.requests')->with('success', 'Perubahan disimpan dan rekomendasi ditambahkan.');
    }

    /**
     * Generate kode unik untuk MasterRekomendasi berdasarkan kategori kecil.
     * Jika kategori tidak ditemukan, gunakan prefix 'GEN'.
     */
    private function generateKodeForKategori(?int $kategoriId): string
    {
        $prefix = 'GEN';
        if ($kategoriId) {
            $kategori = KategoriMasalah::find($kategoriId);
            if ($kategori && !empty($kategori->kode)) {
                $prefix = strtoupper(trim($kategori->kode));
            }
        }

        $latest = MasterRekomendasi::where('kode', 'like', $prefix . '\_%')
            ->orderBy('kode', 'desc')
            ->value('kode');

        $nextNum = 1;
        if ($latest) {
            $parts = explode('_', $latest);
            $last = end($parts);
            if (ctype_digit($last)) {
                $nextNum = (int) $last + 1;
            }
        }

        return sprintf('%s_%02d', $prefix, $nextNum);
    }
}
