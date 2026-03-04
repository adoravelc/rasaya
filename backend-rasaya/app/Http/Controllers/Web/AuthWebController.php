<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthWebController extends Controller
{
    public function showLogin()
    {
        if ((bool) config('auth.guest_only_mode', false)) {
            return redirect()->route('home');
        }

        return view('auth.login');
    }

    public function doLogin(Request $request)
    {
        if ((bool) config('auth.guest_only_mode', false)) {
            return redirect()->route('home')->withErrors([
                'guest' => 'Mode demo hanya mendukung login guest.',
            ]);
        }

        $request->session()->forget(['guest_mode', 'guest_role']);

        $cred = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($cred, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            // tentukan tujuan redirect
            if ($user->role === 'admin') {
                return redirect()->route('admin.dashboard');
            }

            if ($user->role === 'guru') {
                $jenis = optional($user->guru)->jenis;
                if ($jenis === 'bk')
                    return redirect()->route('guru.bk.dashboard'); // /guru/bk
                if ($jenis === 'wali_kelas')
                    return redirect()->route('guru.wk.dashboard'); // /guru/wk
                Auth::logout();
                return back()->withErrors(['identifier' => 'Akun guru belum punya jenis (bk/wali_kelas).'])
                    ->onlyInput('identifier');
            }

            if ($user->role === 'siswa') {
                return redirect()->route('siswa.dashboard');
            }

            // fallback
            return redirect('/login');
        }

        return back()->withErrors(['identifier' => 'Username atau Password salah, mohon dicek kembali.'])->onlyInput('identifier');
    }

    public function logout(Request $request)
    {
        // Logout event listener will handle history update

        $request->session()->forget(['guest_mode', 'guest_role']);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
