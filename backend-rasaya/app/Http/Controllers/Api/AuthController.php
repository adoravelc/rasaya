<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserLoginHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        $user = User::where('identifier', $data['identifier'])->first();

        if (!$user || !\Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'identifier' => ['Identifier atau password salah.'],
            ]);
        }

        $history = $user->loginHistories()->create([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'logged_in_at' => Carbon::now(),
        ]);

        $token = $user->createToken($data['device_name'] ?? 'flutter')->plainTextToken;
        
        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'identifier' => $user->identifier,
                'role' => $user->role,
                'name' => $user->name,
                'email' => $user->email
            ]
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $data = $user->toArray();
        
        // Jika role siswa, tambahkan info NIS dan kelas aktif
        if ($user->role === 'siswa' && $user->siswa) {
            $siswa = $user->siswa;
            // ambil roster aktif terbaru
            $aktif = \App\Models\SiswaKelas::with(['kelas.tahunAjaran'])
                ->where('siswa_id', $siswa->user_id)
                ->where('is_active', true)
                ->latest('id')
                ->first();
            
            if ($aktif && $aktif->kelas) {
                $kelas = $aktif->kelas;
                $tahunAjaran = $kelas->tahunAjaran;
                $data['nis'] = $user->identifier; // NIS dari identifier
                $data['kelas_label'] = $kelas->label . ' — ' . ($tahunAjaran->nama ?? '');
            }
        }
        
        return response()->json($data);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        // tandai waktu logout pada history terakhir
        $last = $user->loginHistories()->latest()->first();
        if ($last && !$last->logged_out_at) {
            $last->update(['logged_out_at' => now()]);
        }

        // revoke token saat ini
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required','string'],
            'new_password' => ['required','string','min:6'],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini tidak sesuai.'],
            ]);
        }

        $user->password = Hash::make($data['new_password']);
        $user->save();

        return response()->json(['message' => 'Password berhasil diubah']);
    }
}
