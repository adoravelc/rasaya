<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Guru;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /**
     * POST /api/admin/users
     * Body:
     * {
     *   "identifier": "1234567812345678",
     *   "role": "guru" | "siswa",
     *   "name": "Nama Lengkap",
     *   "email": "opsional@rasaya.id",   // bisa null kalau email nullable
     *   "jenis": "bk" | "wali_kelas"     // WAJIB jika role=guru
     * }
     */
    public function store(Request $request)
    {
        // Validasi dasar
        $data = $request->validate([
            'identifier' => ['required','string','max:100','unique:users,identifier'],
            'role'       => ['required', Rule::in(['guru','siswa'])],
            'name'       => ['required','string','max:100'],
            'email'      => ['nullable','email','max:150','unique:users,email'],
            'jenis'      => ['nullable', Rule::in(['bk','wali_kelas'])], // hanya untuk guru
            'jenis_kelamin' => ['required', Rule::in(['L','P'])],
        ]);

        // Aturan tambahan: jika role=guru maka jenis wajib
        if ($data['role'] === 'guru' && empty($data['jenis'])) {
            return response()->json([
                'message' => 'Kolom jenis (bk|wali_kelas) wajib untuk role guru.'
            ], 422);
        }

        // Transaksi: buat user + tabel peran
        [$user, $plain] = DB::transaction(function () use ($data) {
            $plain = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $user = User::create([
                'identifier' => $data['identifier'],
                'role'       => $data['role'],
                'name'       => $data['name'],
                'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
                'email'      => $data['email'] ?? null,
                'password'   => $plain,
                'initial_password' => Crypt::encryptString($plain),
            ]);

            if ($data['role'] === 'guru') {
                Guru::create([
                    'user_id' => $user->id,
                    'jenis'   => $data['jenis'], // bk / wali_kelas
                ]);
            } else {
                Siswa::create(['user_id' => $user->id]);
            }

            return [$user->fresh(), $plain];
        });

        return response()->json([
            'message' => 'User created',
            'user' => [
                'id' => $user->id,
                'identifier' => $user->identifier,
                'role' => $user->role,
                'name' => $user->name,
                'email' => $user->email,
                'initial_password_token' => $plain,
            ]
        ], 201);
    }
}
