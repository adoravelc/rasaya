<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Guru;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
     *   "password": "rahasia",
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
            'password'   => ['required','string','min:6'],
            'jenis'      => ['nullable', Rule::in(['bk','wali_kelas'])], // hanya untuk guru
        ]);

        // Aturan tambahan: jika role=guru maka jenis wajib
        if ($data['role'] === 'guru' && empty($data['jenis'])) {
            return response()->json([
                'message' => 'Kolom jenis (bk|wali_kelas) wajib untuk role guru.'
            ], 422);
        }

        // Transaksi: buat user + tabel peran
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'identifier' => $data['identifier'],
                'role'       => $data['role'],
                'name'       => $data['name'],
                'email'      => $data['email'] ?? null,
                'password'   => Hash::make($data['password']),
            ]);

            if ($data['role'] === 'guru') {
                Guru::create([
                    'user_id' => $user->id,
                    'jenis'   => $data['jenis'], // bk / wali_kelas
                ]);
            } else {
                Siswa::create(['user_id' => $user->id]);
            }

            return $user->fresh();
        });

        return response()->json([
            'message' => 'User created',
            'user' => [
                'id' => $user->id,
                'identifier' => $user->identifier,
                'role' => $user->role,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ], 201);
    }
}
