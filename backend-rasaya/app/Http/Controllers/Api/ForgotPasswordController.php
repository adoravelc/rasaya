<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use App\Mail\PasswordResetLink as PasswordResetLinkMail;

class ForgotPasswordController extends Controller
{
    public function requestReset(Request $request)
    {
        $request->validate([
            'identifier' => ['required_without:email'],
            'email' => ['nullable','email'],
            'method' => ['nullable','in:email,admin'],
        ]);
        $identifier = trim((string) $request->input('identifier'));
        $email = trim((string) $request->input('email'));
        $method = $request->input('method', 'admin');
        $emailEnabled = (bool) config('auth.reset_email_enabled');

        $user = null;
        if ($identifier !== '') {
            $user = User::where('identifier', $identifier)->whereNull('deleted_at')->first();
        }
        if (!$user && $email !== '') {
            $user = User::where('email', $email)->whereNull('deleted_at')->first();
        }
        if ($user) {
            $user->reset_requested_at = now();
            $user->save();

            if ($method === 'email' && $emailEnabled && !empty($user->email)) {
                try {
                    $token = Password::createToken($user);
                    $resetUrl = route('password.reset.show', ['token' => $token]) . '?email=' . urlencode($user->email);
                    Mail::to($user->email)->send(new PasswordResetLinkMail($resetUrl));
                } catch (\Throwable $e) {
                    Log::warning('RESET_EMAIL_FAILED', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        // Always return success to avoid enumeration
        return response()->json(['status' => 'ok']);
    }
}
