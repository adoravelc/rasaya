<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Guru;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class GuruController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Guru::with('user:id,name,email,identifier')->paginate(20);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'identifier' => 'required|unique:users,identifier',
            'password' => 'required|min:6',
            'jenis' => 'required|in:bk,wali_kelas',
        ]);
        $u = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'identifier' => $data['identifier'],
            'role' => 'guru',
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);
        $g = Guru::create(['user_id' => $u->id, 'jenis' => $data['jenis']]);
        return response()->json($g->load('user'), 201);
    }

    public function show(Guru $guru)
    {
        return $guru->load('user');
    }

    public function update(Request $r, Guru $guru)
    {
        $data = $r->validate(['jenis' => 'required|in:bk,wali_kelas']);
        $guru->update($data);
        return $guru->load('user');
    }

    public function destroy(Guru $guru)
    {
        DB::transaction(function () use ($guru) {
            $guru->delete();                    // soft-delete guru
            $guru->user()->delete();            // soft-delete user terkait
        });
        return response()->noContent();
    }

    // List yang terhapus (opsional)
    public function trash()
    {
        return \App\Models\Guru::onlyTrashed()
            ->with(['user' => fn($q) => $q->withTrashed()])
            ->paginate(20);
    }

    // Restore
    public function restore($id)
    {
        $guru = \App\Models\Guru::onlyTrashed()->findOrFail($id);
        DB::transaction(function () use ($guru) {
            $guru->restore();
            $guru->user()->withTrashed()->restore();
        });
        return response()->json(['ok' => true]);
    }

    // Hapus permanen
    public function forceDestroy($id)
    {
        $guru = \App\Models\Guru::onlyTrashed()->findOrFail($id);
        DB::transaction(function () use ($guru) {
            $guru->forceDelete();
            $guru->user()->withTrashed()->forceDelete();
        });
        return response()->noContent();
    }
}
