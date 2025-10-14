<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InputSiswa;
use App\Http\Requests\StoreInputSiswaRequest;

class InputSiswaController extends Controller
{
    public function index(Request $r)
    {
        $user = $r->user();

        if ($user->role === 'siswa') {
            // pakai user_id (bukan siswas.id)
            $siswaUserId = optional($user->siswa)->user_id;
            abort_if(!$siswaUserId, 403);

            return InputSiswa::with(['kategoris'])
                ->where('siswa_id', $siswaUserId)   // ← ini kuncinya
                ->orderByDesc('tanggal')
                ->paginate(10);
        }

        // admin/guru
        $q = InputSiswa::with(['kategoris', 'siswa.user']);
        if ($r->filled('siswa_id')) {
            $q->where('siswa_id', (int) $r->input('siswa_id')); // ini juga user_id
        }
        if ($r->filled('tanggal')) {
            $q->whereDate('tanggal', $r->date('tanggal'));
        }
        return $q->orderByDesc('tanggal')->paginate(10);
    }

    public function store(StoreInputSiswaRequest $r)
    {
        $user = $r->user();
        $data = $r->validated();

        // pelapor = user_id
        $siswaUserId = $data['siswa_id'] ?? optional($user->siswa)->user_id;
        abort_if(!$siswaUserId, 403);

        if (
            !empty($data['siswa_dilapor_id']) &&
            (int) $data['siswa_dilapor_id'] === (int) $siswaUserId
        ) {
            return response()->json(['message' => 'Tidak boleh melaporkan diri sendiri.'], 422);
        }

        $row = InputSiswa::create([
            'siswa_id' => $siswaUserId,                 // ← user_id
            'siswa_dilapor_id' => $data['siswa_dilapor_id'] ?? null, // juga user_id
            'tanggal' => $data['tanggal'] ?? now()->toDateString(),
            'teks' => $data['teks'],
            'avg_emosi' => $data['avg_emosi'] ?? null,
            'gambar' => $data['gambar'] ?? null,
            'status_upload' => (int) ($data['status_upload'] ?? 1),
            'meta' => $data['meta'] ?? null,
        ]);

        if (!empty($data['kategori_ids'])) {
            $row->kategoris()->sync($data['kategori_ids']);
        }

        return response()->json($row->load(['kategoris', 'siswa.user']), 201);
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
