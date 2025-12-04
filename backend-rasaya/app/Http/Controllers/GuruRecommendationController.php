<?php

namespace App\Http\Controllers;

use App\Models\MasterRekomendasi;
use App\Models\RekomendasiRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GuruRecommendationController extends Controller
{
    /**
     * List existing master recommendations for a given small category.
     */
    public function listByKategori(int $kategoriId)
    {
        // MasterRekomendasi has relation 'kategoris' many-to-many; filter by kategori kecil id
        $items = MasterRekomendasi::whereHas('kategoris', function ($q) use ($kategoriId) {
            $q->where('kategori_masalahs.id', $kategoriId);
        })
        ->select(['id', 'judul', 'deskripsi', 'severity'])
        ->orderBy('judul')
        ->get();

        return response()->json(['items' => $items]);
    }

    /**
     * Submit a request to admin to add a new recommendation for a category.
     */
    public function submitRequest(Request $request)
    {
        $request->validate([
            'kategori_masalah_id' => 'required|exists:kategori_masalahs,id',
            'judul' => 'required|string|max:200',
            'deskripsi' => 'nullable|string',
            'severity' => 'required|in:low,medium,high',
            'rules' => 'nullable|array',
        ]);

        $req = RekomendasiRequest::create([
            'kategori_masalah_id' => (int)$request->kategori_masalah_id,
            'requested_by' => Auth::id(),
            'judul' => $request->judul,
            'deskripsi' => $request->deskripsi,
            'severity' => $request->severity,
            'rules' => $request->rules,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Permintaan rekomendasi baru dikirim ke admin',
            'request' => $req,
        ], 201);
    }
}
