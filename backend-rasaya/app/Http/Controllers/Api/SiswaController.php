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
