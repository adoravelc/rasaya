<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSiswaRequest;
use App\Http\Requests\UpdateSiswaRequest;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminSiswaController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));
        $siswas = Siswa::with('user')
            ->when($q, function ($q2) use ($q) {
                $q2->whereHas('user', function ($u) use ($q) {
                    $like = "%{$q}%";
                    $u->where('name', 'like', $like)
                        ->orWhere('identifier', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->orderByDesc('user_id')
            ->paginate(15)
            ->withQueryString();
        return view('roles.admin.siswa.index', compact('siswas', 'q'));
    }

    public function store(StoreSiswaRequest $request)
    {
        $data = $request->validated();
        $user = User::create([
            'identifier' => $data['identifier'],
            'role' => 'siswa',
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password'] ?? 'password123'),
        ]);
        Siswa::create(['user_id' => $user->id]);
        return redirect()->route('admin.siswa.index')->with('success', 'Siswa berhasil ditambahkan.');
    }

    public function update(UpdateSiswaRequest $request, $userId)
    {
        $data = $request->validated();
        $user = User::where('id', $userId)->where('role', 'siswa')->firstOrFail();
        $user->identifier = $data['identifier'];
        $user->name = $data['name'];
        $user->email = $data['email'];
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        return redirect()->route('admin.siswa.index')->with('success', 'Siswa berhasil diperbarui.');
    }

    public function destroy($userId)
    {
        $user = User::where('id', $userId)->where('role', 'siswa')->firstOrFail();
        $user->delete();
        if ($user->siswa) $user->siswa->delete();
        return redirect()->route('admin.siswa.index')->with('success', 'Siswa diarsipkan.');
    }
}
