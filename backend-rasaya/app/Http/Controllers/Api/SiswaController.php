<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class SiswaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Siswa::with('user:id,name,email,identifier')->paginate(20);
    }

    // app/Http/Controllers/Api/SiswaController.php
    public function listSimple(Request $r)
    {
        $me = $r->user();
        $myUserId = $me->id;

        // optional: filter q
        $q = trim((string) $r->query('q'));
        $siswas = \App\Models\Siswa::with(['user:id,name,identifier'])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->whereHas('user', function ($uq) use ($q) {
                    $uq->where('name', 'like', "%{$q}%")
                        ->orWhere('identifier', 'like', "%{$q}%");
                });
            })
            ->limit(1000)
            ->get();

        // Ambil mapping kelas aktif/terbaru per siswa
        $ids = $siswas->pluck('user_id')->all();
        $skAll = \App\Models\SiswaKelas::with(['kelas.jurusan', 'tahunAjaran'])
            ->whereIn('siswa_id', $ids)
            ->get()
            ->groupBy('siswa_id');

        $rows = $siswas->filter(fn($s) => $s->user_id !== $myUserId)->map(function ($s) use ($skAll) {
            $kelass = $skAll->get($s->user_id) ?? collect();
            // pilih: yang aktif; jika tidak ada, ambil yang terbaru by joined_at desc
            $chosen = $kelass->sortByDesc(function ($x) {
                return [($x->is_active ? 1 : 0), $x->joined_at ?? $x->id];
            })->first();

            $kelasLabel = null;
            if ($chosen && $chosen->kelas) {
                $tingkat = $chosen->kelas->tingkat;
                $jurusan = optional($chosen->kelas->jurusan)->nama;
                $rombel  = $chosen->kelas->rombel;
                $taName  = optional($chosen->tahunAjaran)->nama ?? optional($chosen->tahunAjaran)->tahun;
                $base = trim(implode(' ', array_filter([$tingkat, $jurusan, $rombel])));
                $kelasLabel = $taName ? "$base ($taName)" : $base;
            }

            return [
                'id' => $s->user_id, // user_id tetap disediakan
                'user_id' => $s->user_id,
                'nama' => optional($s->user)->name ?? 'Tanpa Nama',
                // kirimkan siswa_kelas_id bila ada agar frontend bisa gunakan untuk pelaporan teman
                'siswa_kelas_id' => $chosen->id ?? null,
                'kelas_label' => $kelasLabel,
            ];
        })->values();

        // optional: sort by kelas lalu nama di server juga
        $rows = $rows->sort(function ($a, $b) {
            $ka = strtoupper($a['kelas_label'] ?? '');
            $kb = strtoupper($b['kelas_label'] ?? '');
            if ($ka === $kb) {
                return strcasecmp($a['nama'] ?? '', $b['nama'] ?? '');
            }
            return $ka <=> $kb;
        })->values();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'identifier' => 'required|unique:users,identifier',
            'password' => 'required|min:6',
        ]);
        $u = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'identifier' => $data['identifier'],
            'role' => 'siswa',
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);
        $s = Siswa::create(['user_id' => $u->id]);
        return response()->json($s->load('user'), 201);
    }

    public function show(Siswa $siswa)
    {
        return $siswa->load('user');
    }

    public function update(Request $r, Siswa $siswa)
    {
        $data = $r->validate(['name' => 'sometimes', 'email' => 'sometimes|email', 'password' => 'nullable|min:6']);
        $u = $siswa->user;
        if (isset($data['name']))
            $u->name = $data['name'];
        if (isset($data['email']))
            $u->email = $data['email'];
        if (!empty($data['password']))
            $u->password = Hash::make($data['password']);
        $u->save();
        return $siswa->load('user');
    }

    public function destroy(Siswa $siswa)
    {
        DB::transaction(function () use ($siswa) {
            $siswa->delete();                    // soft-delete siswa
            $siswa->user()->delete();            // soft-delete user
        });
        return response()->noContent();
    }

    public function trash()
    {
        return \App\Models\Siswa::onlyTrashed()
            ->with(['user' => fn($q) => $q->withTrashed()])
            ->paginate(20);
    }

    public function restore($id)
    {
        $siswa = \App\Models\Siswa::onlyTrashed()->findOrFail($id);
        DB::transaction(function () use ($siswa) {
            $siswa->restore();
            $siswa->user()->withTrashed()->restore();
        });
        return response()->json(['ok' => true]);
    }

    public function forceDestroy($id)
    {
        $siswa = \App\Models\Siswa::onlyTrashed()->findOrFail($id);
        DB::transaction(function () use ($siswa) {
            $siswa->forceDelete();
            $siswa->user()->withTrashed()->forceDelete();
        });
        return response()->noContent();
    }
}
