<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class AdminUserManagementController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));
        $role = $request->input('role'); // null|''|guru|siswa

        $activeTa = TahunAjaran::aktif()->first();

        $query = User::query()
            ->whereIn('role', ['guru', 'siswa'])
            ->when($role, fn($q2) => $q2->where('role', $role))
            ->when($q, function ($q2) use ($q) {
                $like = "%{$q}%";
                $q2->where(function ($w) use ($like) {
                    $w->where('name', 'like', $like)
                      ->orWhere('identifier', 'like', $like)
                      ->orWhere('email', 'like', $like);
                });
            })
            ->with(['guru', 'siswa.kelass' => function ($rel) use ($activeTa) {
                if ($activeTa) {
                    $rel->where('siswa_kelass.tahun_ajaran_id', $activeTa->id)
                        ->with('jurusan');
                }
            }]);

        // Sorting rules
        if ($role === 'guru') {
            // BK first, then Wali Kelas
            $query->orderByRaw("CASE WHEN EXISTS (SELECT 1 FROM gurus g WHERE g.user_id = users.id AND g.jenis = 'bk') THEN 0 ELSE 1 END ASC")
                  ->orderBy('name');
        } elseif ($role === 'siswa') {
            // Siswa with no class at top (for active tahun ajaran), then by name
            $taId = $activeTa?->id ?? 0;
            $query->orderByRaw(
                "CASE WHEN EXISTS (
                    SELECT 1
                    FROM siswas s
                    JOIN siswa_kelass sk ON sk.siswa_id = s.user_id AND sk.tahun_ajaran_id = ?
                    WHERE s.user_id = users.id AND s.deleted_at IS NULL
                ) THEN 1 ELSE 0 END ASC",
                [$taId]
            )->orderBy('name');
        } else {
            // Default: group by role (guru first), then name
            $query->orderByRaw("CASE WHEN role = 'guru' THEN 0 ELSE 1 END ASC")
                  ->orderBy('name');
        }

        // Prioritize users with reset_requested_at at top
        $users = $query->orderByRaw('CASE WHEN reset_requested_at IS NOT NULL THEN 0 ELSE 1 END ASC')
            ->orderByDesc('reset_requested_at')
            ->paginate(15)->withQueryString();

        return view('roles.admin.users.index', compact('users', 'q', 'role', 'activeTa'));
    }

    public function trashed(Request $request)
    {
        $q = trim((string) $request->input('q'));
        $users = User::onlyTrashed()
            ->whereIn('role', ['guru', 'siswa'])
            ->when($q, function ($q2) use ($q) {
                $like = "%{$q}%";
                $q2->where(function ($w) use ($like) {
                    $w->where('name', 'like', $like)
                      ->orWhere('identifier', 'like', $like)
                      ->orWhere('email', 'like', $like);
                });
            })
            ->orderBy('role')->orderBy('name')
            ->paginate(15)->withQueryString();
        return view('roles.admin.users.trashed', compact('users', 'q'));
    }

    public function restore($userId)
    {
        $user = User::withTrashed()->where('id', $userId)->firstOrFail();
        $user->restore();
        if ($user->role === 'guru' && $user->guru()->withTrashed()->exists()) {
            $user->guru()->withTrashed()->first()->restore();
        }
        if ($user->role === 'siswa' && $user->siswa()->withTrashed()->exists()) {
            $user->siswa()->withTrashed()->first()->restore();
        }
        return redirect()->route('admin.users.trashed')->with('success', 'User dipulihkan.');
    }

    public function forceDelete($userId)
    {
        $user = User::withTrashed()->where('id', $userId)->firstOrFail();
        if ($user->role === 'guru' && $user->guru()->withTrashed()->exists()) {
            $user->guru()->withTrashed()->first()->forceDelete();
        }
        if ($user->role === 'siswa' && $user->siswa()->withTrashed()->exists()) {
            $user->siswa()->withTrashed()->first()->forceDelete();
        }
        $user->forceDelete();
        return redirect()->route('admin.users.trashed')->with('success', 'User dihapus permanen.');
    }

    public function resetPassword($userId)
    {
        $user = User::where('id', $userId)->firstOrFail();
        // Only allow if a reset was requested (within 7 days)
        if (!$user->reset_requested_at || $user->reset_requested_at->lt(now()->subDays(7))) {
            return back()->with('error', 'Reset password tidak diizinkan. User belum mengajukan atau permohonan kadaluarsa.');
        }

        $plain = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $user->password = $plain; // Auto-hashed by User model cast
        $user->initial_password = Crypt::encryptString($plain);
        $user->password_changed_at = null;
        $user->reset_requested_at = null;
        $user->save();

        return back()->with('success', 'Password telah direset. Sampaikan password baru ke pengguna.');
    }
}
