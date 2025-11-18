<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function show(string $token, Request $request)
    {
        $email = (string) $request->query('email');
        // Do not reveal existence; just render form with token+email
        return view('auth.reset_password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function submit(Request $request)
    {
        $request->validate([
            'email' => ['required','email'],
            'token' => ['required','string'],
            'password' => ['required','confirmed','min:8'],
        ]);

        $email = (string) $request->input('email');
        $token = (string) $request->input('token');

        $user = User::where('email', $email)->whereNull('deleted_at')->first();
        if (!$user) {
            // Always respond success-like to avoid enumeration
            return redirect()->route('login')->with('status', 'Password berhasil direset');
        }

        // Validate token using broker
        if (!Password::tokenExists($user, $token)) {
            return back()->withErrors(['token' => 'Token tidak valid atau sudah kedaluwarsa.']);
        }

        // Update password
        $user->password = Hash::make($request->input('password'));
        $user->password_changed_at = now();
        $user->initial_password = null;
        $user->reset_requested_at = null;
        $user->save();

        // Invalidate token
        Password::deleteToken($user);

        return redirect()->route('login')->with('status', 'Password berhasil direset, silakan masuk.');
    }
}
