<?php

namespace App\Http\Controllers;

use App\Models\RekomendasiRequest;
use App\Models\MasterRekomendasi;
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
        // Create MasterRekomendasi entry
        $mr = MasterRekomendasi::create([
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
        $req->update(['status' => 'approved', 'approved_at' => now()]);
        return back()->with('success', 'Rekomendasi ditambahkan dan permintaan disetujui.');
    }

    public function reject(int $id)
    {
        $req = RekomendasiRequest::findOrFail($id);
        $req->update(['status' => 'rejected', 'rejected_at' => now()]);
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

        // Save to MasterRekomendasi (edited version)
        $mr = MasterRekomendasi::create([
            'judul' => $request->input('judul'),
            'deskripsi' => $request->input('deskripsi'),
            'severity' => $request->input('severity') ?: 'low',
            'is_active' => true,
            'rules' => $req->rules ?? [],
        ]);
        if ($req->kategori_masalah_id) {
            $mr->kategoris()->attach($req->kategori_masalah_id);
        }

        $req->update(['status' => 'approved', 'approved_at' => now()]);
        return redirect()->route('admin.rekomendasi.requests')->with('success', 'Perubahan disimpan dan rekomendasi ditambahkan.');
    }
}
