<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Guru;
use App\Models\Siswa;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Admin
        User::updateOrCreate(['email' => 'admin@rasaya.id'], [
            'identifier' => 'adminrasaya',
            'role' => 'admin',
            'name' => 'Admin RASAYA',
            'password' => Hash::make('1'),
            'email_verified_at' => now(),
        ]);

        // Guru BK
        $guruUser = User::updateOrCreate(['email' => 'gurubk@rasaya.id'], [
            'identifier' => 'bk',
            'role' => 'guru',
            'name' => 'Natalia',
            'password' => Hash::make('1'),
            'email_verified_at' => now(),
        ]);
        Guru::updateOrCreate(['user_id' => $guruUser->id], ['jenis' => 'bk']);

        // Guru Wali Kelas
        $guruUser = User::updateOrCreate(['email' => 'guruwk@rasaya.id'], [
            'identifier' => 'wk',
            'role' => 'guru',
            'name' => 'Alfonsius',
            'password' => Hash::make('1'),
            'email_verified_at' => now(),
        ]);
        Guru::updateOrCreate(['user_id' => $guruUser->id], ['jenis' => 'wali_kelas']);

        // Siswa
        $siswaUser = User::updateOrCreate(['email' => 'siswa@rasaya.id'], [
            'identifier' => '1234',
            'role' => 'siswa',
            'name' => 'Chavelle',
            'password' => Hash::make('1'),
            'email_verified_at' => now(),
        ]);
        Siswa::updateOrCreate(['user_id' => $siswaUser->id], []);

        // Siswa2
        $siswaUser2 = User::updateOrCreate(['email' => 'siswa2@rasaya.id'], [
            'identifier' => '5678',
            'role' => 'siswa',
            'name' => 'Bellatrix',
            'password' => Hash::make('1'),
            'email_verified_at' => now(),
        ]);
        Siswa::updateOrCreate(['user_id' => $siswaUser2->id], []);
    }
}