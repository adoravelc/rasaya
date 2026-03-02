<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GuestAccessController extends Controller
{
    public function home(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('home');
    }

    public function enter(Request $request, string $role)
    {
        $guestConfig = config("auth.guest_accounts.{$role}");

        if (!$guestConfig || empty($guestConfig['identifier']) || empty($guestConfig['password'])) {
            return redirect()->route('home')->withErrors([
                'guest' => 'Akun guest belum dikonfigurasi. Hubungi admin untuk melengkapi GUEST_* di .env.',
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $request->session()->put('guest_mode', true);
        $request->session()->put('guest_role', $role);

        $ok = Auth::attempt([
            'identifier' => $guestConfig['identifier'],
            'password' => $guestConfig['password'],
        ], false);

        if (!$ok) {
            $request->session()->forget(['guest_mode', 'guest_role']);

            return redirect()->route('home')->withErrors([
                'guest' => 'Login guest gagal. Pastikan akun demo tersedia dan password di .env benar.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        if (!$user) {
            return redirect()->route('home');
        }

        if ($role === 'guru-bk') {
            $jenis = optional($user->guru)->jenis;
            if ($user->role !== 'guru' || $jenis !== 'bk') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('home')->withErrors([
                    'guest' => 'Akun guest Guru BK tidak valid. Pastikan role=guru dan jenis=bk.',
                ]);
            }
        }

        if ($role === 'siswa' && $user->role !== 'siswa') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('home')->withErrors([
                'guest' => 'Akun guest Siswa tidak valid. Pastikan role=siswa.',
            ]);
        }

        $targetRoute = match ($role) {
            'guru-bk' => 'guru.bk.dashboard',
            'siswa' => 'siswa.dashboard',
            default => ($guestConfig['redirect_route'] ?? 'dashboard'),
        };

        return redirect()->route($targetRoute);
    }

    public function exit(Request $request)
    {
        $next = (string) $request->query('next', '');

        $request->session()->forget(['guest_mode', 'guest_role']);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $flutterWebUrl = rtrim((string) config('app.flutter_web_url', ''), '/');
        if ($next !== '' && $flutterWebUrl !== '' && str_starts_with($next, $flutterWebUrl)) {
            return redirect()->away($next);
        }

        return redirect()->route('home')->with('status', 'Sesi guest berhasil direset. Silakan login ulang.');
    }
}
