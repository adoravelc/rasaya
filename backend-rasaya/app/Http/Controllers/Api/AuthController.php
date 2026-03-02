<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GuestSandboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function loginGuestSiswa(Request $request)
    {
        $guestConfig = config('auth.guest_accounts.siswa');

        if (!$guestConfig || empty($guestConfig['identifier']) || empty($guestConfig['password'])) {
            return response()->json([
                'message' => 'Akun guest siswa belum dikonfigurasi.',
            ], 503);
        }

        $user = User::where('identifier', $guestConfig['identifier'])->first();

        if (!$user || !Hash::check($guestConfig['password'], $user->password) || $user->role !== 'siswa') {
            return response()->json([
                'message' => 'Akun guest siswa tidak valid. Hubungi admin.',
            ], 401);
        }

        $history = $user->loginHistories()->create([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'logged_in_at' => Carbon::now(),
        ]);

        $token = $user->createToken($request->input('device_name', 'flutter-guest'))->plainTextToken;

        return response()->json([
            'token' => $token,
            'guest_mode' => true,
            'user' => [
                'id' => $user->id,
                'identifier' => $user->identifier,
                'role' => $user->role,
                'name' => $user->name,
                'email' => $user->email,
                'jenis_kelamin' => $user->jenis_kelamin,
            ]
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        $user = User::where('identifier', $data['identifier'])->first();

        if (!$user || !\Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Username atau Password salah, mohon dicek kembali.',
            ], 401);
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
                'email' => $user->email,
                'jenis_kelamin' => $user->jenis_kelamin,
            ]
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $data = $user->toArray();
        $data['jenis_kelamin'] = $user->jenis_kelamin;
        
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
        // Tambahkan token password (dekripsi) hanya jika user belum pernah mengganti password
        if (!$user->password_changed_at && $user->initial_password) {
            try {
                $data['initial_password_token'] = \Illuminate\Support\Facades\Crypt::decryptString($user->initial_password);
            } catch (\Throwable $e) {
                // ignore dekripsi gagal
            }
        }
        $data['needs_password_update'] = (!$user->password_changed_at && $user->initial_password) ? true : false;
        
        return response()->json($data);
    }

    public function logout(Request $request)
    {
        $sandbox = app(GuestSandboxService::class);
        if ($sandbox->isGuestSiswa($request)) {
            $sandbox->clearForRequest($request);
        }

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

        // Jika masih fase token awal (belum password_changed_at) dan initial_password ada,
        // izinkan penggunaan token awal sebagai current_password tanpa hash check lama.
        $usingInitial = false;
        if (!$user->password_changed_at && $user->initial_password) {
            try {
                $token = \Illuminate\Support\Facades\Crypt::decryptString($user->initial_password);
                if (hash_equals($token, $data['current_password'])) {
                    $usingInitial = true;
                }
            } catch (\Throwable $e) {}
        }

        if (!$usingInitial && !Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini / token tidak sesuai.'],
            ]);
        }

        $user->password = $data['new_password'];
        if (!$user->password_changed_at) {
            $user->password_changed_at = now();
            // hapus initial_password agar tidak bisa diambil lagi
            $user->initial_password = null;
        }
        $user->save();

        return response()->json(['message' => 'Password berhasil diubah']);
    }

    public function changeEmail(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'email' => ['required','email','max:191','unique:users,email,' . $user->id],
        ]);
        $user->email = $data['email'];
        $user->save();
        return response()->json(['message' => 'Email berhasil diperbarui', 'email' => $user->email]);
    }

    public function saveFcmToken(Request $request)
    {
        $data = $request->validate([
            'fcm_token' => ['required', 'string'],
        ]);

        $user = $request->user();
        $user->fcm_token = $data['fcm_token'];
        $user->save();

        return response()->json([
            'message' => 'FCM token berhasil disimpan',
            'token' => $user->fcm_token
        ]);
    }
}
