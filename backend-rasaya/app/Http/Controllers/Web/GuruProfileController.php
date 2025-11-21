<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class GuruProfileController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'guru', 403);
        $user->loadMissing(['guru']);
        return view('roles.guru.profile.index', compact('user'));
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'guru', 403);

        $data = $request->validate([
            'current_password' => ['required'],
            'password' => ['required','min:6','confirmed'],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            return back()->with('error', 'Password saat ini salah.');
        }

        $user->password = $data['password'];
        $user->password_changed_at = now();
        $user->initial_password = null;
        $user->save();

        return redirect()->route('guru.profile.index')->with('success', 'Password berhasil diubah.');
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'guru', 403);

        $data = $request->validate([
            'email' => ['required','email','max:255','unique:users,email,'.$user->id],
        ]);

        $user->email = $data['email'];
        $user->save();

        return back()->with('success', 'Profil berhasil diperbarui.');
    }
}
