<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Seeder;

class GuestAccountSeeder extends Seeder
{
    public function run(): void
    {
        $guestAccounts = config('auth.guest_accounts', []);

        $this->seedGuestGuruBk($guestAccounts['guru-bk'] ?? []);
        $this->seedGuestSiswa($guestAccounts['siswa'] ?? []);
    }

    private function seedGuestGuruBk(array $config): void
    {
        $identifier = (string) ($config['identifier'] ?? 'guest_guru_bk');
        $password = (string) ($config['password'] ?? 'guest12345');

        if ($identifier === '' || $password === '') {
            return;
        }

        $email = $identifier . '@guest.rasaya.local';

        $user = User::withTrashed()->firstOrNew(['identifier' => $identifier]);
        $user->fill([
            'role' => 'guru',
            'name' => 'Guest Guru BK',
            'email' => $user->email ?: $email,
            'password' => $password,
            'initial_password' => $password,
            'email_verified_at' => now(),
        ]);
        $user->save();

        if ($user->trashed()) {
            $user->restore();
        }

        $guru = Guru::withTrashed()->firstOrNew(['user_id' => $user->id]);
        $guru->fill(['jenis' => 'bk']);
        $guru->save();

        if ($guru->trashed()) {
            $guru->restore();
        }

        $siswa = Siswa::withTrashed()->where('user_id', $user->id)->first();
        if ($siswa) {
            $siswa->delete();
        }
    }

    private function seedGuestSiswa(array $config): void
    {
        $identifier = (string) ($config['identifier'] ?? 'guest_siswa');
        $password = (string) ($config['password'] ?? 'guest12345');

        if ($identifier === '' || $password === '') {
            return;
        }

        $email = $identifier . '@guest.rasaya.local';

        $user = User::withTrashed()->firstOrNew(['identifier' => $identifier]);
        $user->fill([
            'role' => 'siswa',
            'name' => 'Guest Siswa',
            'email' => $user->email ?: $email,
            'password' => $password,
            'initial_password' => $password,
            'email_verified_at' => now(),
        ]);
        $user->save();

        if ($user->trashed()) {
            $user->restore();
        }

        $siswa = Siswa::withTrashed()->firstOrNew(['user_id' => $user->id]);
        $siswa->save();

        if ($siswa->trashed()) {
            $siswa->restore();
        }

        $guru = Guru::withTrashed()->where('user_id', $user->id)->first();
        if ($guru) {
            $guru->delete();
        }
    }
}
