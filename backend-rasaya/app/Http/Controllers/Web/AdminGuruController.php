<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGuruRequest;
use App\Http\Requests\UpdateGuruRequest;
use App\Models\Guru;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminGuruController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));
        $jenis = $request->input('jenis'); // 'bk' | 'wali_kelas' | null

        $gurus = Guru::with(['user'])
            ->when($jenis, fn($q2) => $q2->where('jenis', $jenis))
            ->when($q, function ($q2) use ($q) {
                $q2->whereHas('user', function ($u) use ($q) {
                    $like = "%{$q}%";
                    $u->where('name', 'like', $like)
                        ->orWhere('identifier', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->orderBy('jenis')
            ->paginate(15)
            ->withQueryString();

        return view('roles.admin.guru.index', compact('gurus', 'q', 'jenis'));
    }

    public function store(StoreGuruRequest $request)
    {
        $data = $request->validated();

        // Create user role=guru
        $user = User::create([
            'identifier' => $data['identifier'],
            'role' => 'guru',
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password'] ?? 'password123'),
        ]);

        Guru::create([
            'user_id' => $user->id,
            'jenis' => $data['jenis'],
        ]);

        return redirect()->route('admin.guru.index')->with('success', 'Guru berhasil ditambahkan.');
    }

    public function update(UpdateGuruRequest $request, $userId)
    {
        $data = $request->validated();
        $user = User::where('id', $userId)->where('role', 'guru')->firstOrFail();
        $user->identifier = $data['identifier'];
        $user->name = $data['name'];
        $user->email = $data['email'];
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        $guru = Guru::firstOrCreate(['user_id' => $user->id]);
        $guru->jenis = $data['jenis'];
        $guru->save();

        return redirect()->route('admin.guru.index')->with('success', 'Guru berhasil diperbarui.');
    }

    public function destroy($userId)
    {
        $user = User::where('id', $userId)->where('role', 'guru')->firstOrFail();

        // Soft delete both user and guru
        $user->delete();
        if ($user->guru) {
            $user->guru->delete();
        }

        return redirect()->route('admin.guru.index')->with('success', 'Guru diarsipkan.');
    }
}
