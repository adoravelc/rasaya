<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSiswaRequest;
use App\Http\Requests\UpdateSiswaRequest;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class AdminSiswaController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));
        
        // Get active tahun ajaran
        $activeTa = TahunAjaran::aktif()->first();
        
        $siswas = Siswa::with([
                'user',
                'kelass' => function ($query) use ($activeTa) {
                    if ($activeTa) {
                        $query->where('siswa_kelass.tahun_ajaran_id', $activeTa->id)
                            ->with('jurusan');
                    }
                }
            ])
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
        return view('roles.admin.siswa.index', compact('siswas', 'q', 'activeTa'));
    }

    public function store(StoreSiswaRequest $request)
    {
        $data = $request->validated();
        $plain = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $user = User::create([
            'identifier' => $data['identifier'],
            'role' => 'siswa',
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $plain,
            'initial_password' => Crypt::encryptString($plain),
            'jenis_kelamin' => $data['jenis_kelamin'],
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
        if (array_key_exists('jenis_kelamin', $data)) {
            $user->jenis_kelamin = $data['jenis_kelamin'];
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

    public function trashed()
    {
        $siswas = Siswa::onlyTrashed()->with(['user' => function($q){ $q->withTrashed(); }])->paginate(15);
        return view('roles.admin.siswa.trashed', compact('siswas'));
    }

    public function restore($userId)
    {
        $user = User::withTrashed()->where('id', $userId)->where('role', 'siswa')->firstOrFail();
        $user->restore();
        if ($user->siswa()->withTrashed()->exists()) {
            $user->siswa()->withTrashed()->first()->restore();
        }
        return redirect()->route('admin.siswa.trashed')->with('success', 'Siswa dipulihkan.');
    }

    public function forceDelete($userId)
    {
        $user = User::withTrashed()->where('id', $userId)->where('role', 'siswa')->firstOrFail();
        if ($user->siswa()->withTrashed()->exists()) {
            $user->siswa()->withTrashed()->first()->forceDelete();
        }
        $user->forceDelete();
        return redirect()->route('admin.siswa.trashed')->with('success', 'Siswa dihapus permanen.');
    }

    // Nonaktifkan siswa + nonaktifkan status kelas di tahun ajaran aktif
    public function deactivate(Request $r, int $userId)
    {
        $r->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $siswa = Siswa::where('user_id', $userId)->firstOrFail();
        // Siswa model PK = user_id, gunakan forceFill agar kolom non-fillable bisa terisi
        $siswa->forceFill([
            'is_active' => false,
            'inactive_reason' => $r->input('reason'),
            'inactive_at' => now(),
        ])->save();

        // Nonaktifkan keanggotaan kelas pada tahun ajaran aktif
        if ($ta = TahunAjaran::aktif()->first()) {
            \App\Models\SiswaKelas::where('siswa_id', $siswa->user_id)
                ->where('tahun_ajaran_id', $ta->id)
                ->update(['is_active' => false, 'left_at' => now()]);
        }

        return back()->with('ok', 'Siswa dinonaktifkan.');
    }

    // Aktifkan kembali siswa (opsional: tidak otomatis mengembalikan ke kelas)
    public function activate(Request $r, int $userId)
    {
        $siswa = Siswa::where('user_id', $userId)->firstOrFail();
        $siswa->forceFill([
            'is_active' => true,
            'inactive_reason' => null,
            'inactive_at' => null,
        ])->save();

        return back()->with('ok', 'Siswa diaktifkan kembali.');
    }
}
