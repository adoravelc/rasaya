<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InputSiswa;
use App\Http\Requests\StoreInputSiswaRequest;

class InputSiswaController extends Controller
{
    // Siswa melihat riwayat pribadinya
    public function index(Request $r)
    {
        $user = $r->user();
        if ($user->role === 'siswa') {
            $siswaId = optional($user->siswa)->id;
            abort_if(!$siswaId, 403);
            return InputSiswa::with('kategoris')
                ->where('siswa_id', $siswaId)
                ->orderByDesc('tanggal')
                ->paginate(20);
        }
        // admin/guru: filter by siswa_id (opsional)
        $q = InputSiswa::with(['kategoris', 'siswa.user']);
        if ($r->filled('siswa_id'))
            $q->where('siswa_id', $r->integer('siswa_id'));
        if ($r->filled('tanggal'))
            $q->whereDate('tanggal', $r->date('tanggal'));
        return $q->orderByDesc('tanggal')->paginate(20);
    }

    public function store(StoreInputSiswaRequest $r)
    {
        $user = $r->user();
        // siswa hanya boleh create untuk dirinya sendiri
        $siswaId = $r->input('siswa_id');
        if ($user->role === 'siswa')
            $siswaId = optional($user->siswa)->id;
        abort_if(!$siswaId, 403);

        $data = $r->validated();
        $row = InputSiswa::create([
            'siswa_id' => $siswaId,
            'tanggal' => $data['tanggal'] ?? now()->toDateString(),
            'teks' => $data['teks'],
            'avg_emosi' => $data['avg_emosi'],
            'meta' => $data['meta'] ?? null,
        ]);

        if (!empty($data['kategori_ids'])) {
            $row->kategoris()->sync($data['kategori_ids']);
        }
        return response()->json($row->load('kategoris'), 201);
    }

    public function show(InputSiswa $inputSiswa)
    {
        return $inputSiswa->load(['kategoris', 'siswa.user']);
    }

    // edit/hapus: admin; siswa boleh edit kepunyaan sendiri (opsional)
    public function update(StoreInputSiswaRequest $r, InputSiswa $inputSiswa)
    {
        $user = $r->user();
        if (!($user->role === 'admin' || ($user->role === 'siswa' && $inputSiswa->siswa_id === optional($user->siswa)->id)))
            abort(403);
        $data = $r->validated();
        $inputSiswa->update([
            'tanggal' => $data['tanggal'] ?? $inputSiswa->tanggal,
            'teks' => $data['teks'] ?? $inputSiswa->teks,
            'avg_emosi' => $data['avg_emosi'] ?? $inputSiswa->avg_emosi,
            'meta' => $data['meta'] ?? $inputSiswa->meta,
        ]);
        if (isset($data['kategori_ids']))
            $inputSiswa->kategoris()->sync($data['kategori_ids']);
        return $inputSiswa->load('kategoris');
    }

    public function destroy(Request $r, InputSiswa $inputSiswa)
    {
        $user = $r->user();
        if (!($user->role === 'admin' || ($user->role === 'siswa' && $inputSiswa->siswa_id === optional($user->siswa)->id)))
            abort(403);
        $inputSiswa->delete();  // soft delete
        return response()->noContent();
    }
}
