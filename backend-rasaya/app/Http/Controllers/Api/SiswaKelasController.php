<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SiswaKelasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $r)
    {
        $r->validate([
            'kelas_id' => 'required|exists:kelass,id',
            'tahun_ajaran_id' => 'required|exists:tahun_ajarans,id',
            'q' => 'sometimes|string',
            'active_only' => 'sometimes|boolean',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $kelas = Kelas::findOrFail($r->kelas_id);

        // pastikan aksesnya valid (admin / wali kelas)
        $this->authorize('view', $kelas);

        $activeOnly = $r->boolean('active_only', true);
        $perPage = (int) ($r->per_page ?? 20);

        $query = $kelas->siswas()
            ->wherePivot('tahun_ajaran_id', $r->tahun_ajaran_id)
            ->when($activeOnly, fn($q) => $q->wherePivot('is_active', true))
            ->with('user:id,name,identifier,email')
            // urutkan berdasarkan nama user
            ->orderBy(
                User::select('name')->whereColumn('users.id', 'siswas.user_id')
            );

        // pencarian di nama / identifier
        if ($r->filled('q')) {
            $q = $r->q;
            $query->whereHas('user', function ($uq) use ($q) {
                $uq->where('name', 'like', "%{$q}%")
                    ->orWhere('identifier', 'like', "%{$q}%");
            });
        }

        $rows = $query->paginate($perPage)->through(function ($siswa) {
            return [
                'id' => $siswa->user_id, // PK siswa = user_id
                'name' => $siswa->user->name,
                'identifier' => $siswa->user->identifier,
                'email' => $siswa->user->email,
                'pivot' => [
                    'tahun_ajaran_id' => $siswa->pivot->tahun_ajaran_id,
                    'is_active' => (bool) $siswa->pivot->is_active,
                    'joined_at' => $siswa->pivot->joined_at,
                    'left_at' => $siswa->pivot->left_at,
                ],
            ];
        });

        return response()->json([
            'kelas' => [
                'id' => $kelas->id,
                'label' => $kelas->label,
            ],
            'filters' => [
                'tahun_ajaran_id' => (int) $r->tahun_ajaran_id,
                'q' => $r->q ?? null,
                'active_only' => $activeOnly,
                'per_page' => $perPage,
            ],
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
        ]);
    }

    // assign siswa ke kelas
    public function store(Request $r)
    {
        $data = $r->validate([
            'tahun_ajaran_id' => 'required|exists:tahun_ajarans,id',
            'kelas_id' => 'required|exists:kelass,id',
            'siswa_id' => 'required|exists:siswas,id',
        ]);
        $s = Siswa::findOrFail($data['siswa_id']);
        $s->kelass()->syncWithoutDetaching([
            $data['kelas_id'] => [
                'tahun_ajaran_id' => $data['tahun_ajaran_id'],
                'is_active' => true,
                'joined_at' => now()->toDateString()
            ],
        ]);
        return response()->json(['ok' => true], 201);
    }

    // drop/keluar
    public function destroy(Request $r)
    {
        $data = $r->validate([
            'tahun_ajaran_id' => 'required|exists:tahun_ajarans,id',
            'kelas_id' => 'required|exists:kelass,id',
            'siswa_id' => 'required|exists:siswas,id',
        ]);
        $s = Siswa::findOrFail($data['siswa_id']);
        $s->kelass()->updateExistingPivot($data['kelas_id'], [
            'is_active' => false,
            'left_at' => now()->toDateString()
        ]);
        return response()->noContent();
    }
}
