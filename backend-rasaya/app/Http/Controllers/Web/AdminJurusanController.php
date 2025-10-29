<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminJurusanController extends Controller
{
    public function index(Request $request)
    {
        $activeTa = $request->input('tahun_ajaran_id') ?: TahunAjaran::aktif()->value('id');
        $items = Jurusan::where('tahun_ajaran_id', $activeTa)
            ->orderBy('nama')
            ->get();
        return response()
            ->json(['data' => $items])
            // Prevent client/proxy caching; ensure fresh list after create/update
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'exists:tahun_ajarans,id'],
            'nama' => ['required', 'string', 'max:100',
                Rule::unique('jurusans', 'nama')->where(fn($q)=>$q->where('tahun_ajaran_id',$request->tahun_ajaran_id))
            ],
        ]);
        // Normalize name: trim and collapse inner spaces
        $data['nama'] = preg_replace('/\s+/', ' ', trim($data['nama']));
        $row = Jurusan::create($data);
        return response()->json(['ok'=>true, 'data'=>$row], 201);
    }

    public function update(Request $request, Jurusan $jurusan)
    {
        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'exists:tahun_ajarans,id'],
            'nama' => ['required', 'string', 'max:100',
                Rule::unique('jurusans', 'nama')->ignore($jurusan->id)
                    ->where(fn($q)=>$q->where('tahun_ajaran_id',$request->tahun_ajaran_id))
            ],
        ]);
        $data['nama'] = preg_replace('/\s+/', ' ', trim($data['nama']));
        $jurusan->update($data);
        return response()->json(['ok'=>true, 'data'=>$jurusan]);
    }

    public function destroy(Jurusan $jurusan)
    {
        $jurusan->delete();
        return response()->json(['ok'=>true]);
    }
}
