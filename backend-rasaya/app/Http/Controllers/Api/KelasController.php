<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKelasRequest;
use App\Http\Requests\UpdateKelasRequest;
use App\Models\Kelas;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $q = Kelas::with(['tahunAjaran', 'waliGuru']);
        if ($request->filled('tahun_ajaran_id')) {
            $q->where('tahun_ajaran_id', $request->tahun_ajaran_id);
        }
        return $q->orderBy('tingkat')->orderBy('nama')->paginate(20);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreKelasRequest $request)
    {
        $kelas = Kelas::create($request->validated());
        return response()->json($kelas->load(['tahunAjaran', 'waliGuru']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Kelas $kelas)
    {
        return $kelas->load(['tahunAjaran', 'waliGuru']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateKelasRequest $request, Kelas $kelas)
    {
        $kelas->update($request->validated());
        return $kelas->load(['tahunAjaran', 'waliGuru']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Kelas $kelas)
    {
        $kelas->delete();
        return response()->noContent();
    }
}
