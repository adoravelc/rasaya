<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SiswaKelas;
use App\Models\TahunAjaran;
use App\Models\Kelas;
use App\Models\Siswa;

class SiswaKelasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

    public function run(): void
    {
        // Ambil tahun ajaran dan kelas yang sudah ada
        $ta = TahunAjaran::query()->latest('id')->first();
        $kelas = Kelas::query()->first();

        if (!$ta) {
            $this->command->warn('TahunAjaran belum ada. Seed tahun_ajarans dulu.');
            return;
        }
        if (!$kelas) {
            $this->command->warn('Kelas belum ada. Seed kelass dulu.');
            return;
        }

        $siswas = Siswa::query()->get();
        if ($siswas->isEmpty()) {
            $this->command->warn('Tidak ada data siswa pada tabel siswas.');
            return;
        }

        $created = 0;
        foreach ($siswas as $s) {
            // siswa_kelass.siswa_id merefer ke siswas.user_id (users.id)
            $row = SiswaKelas::firstOrCreate(
                [
                    'tahun_ajaran_id' => $ta->id,
                    'kelas_id' => $kelas->id,
                    'siswa_id' => $s->user_id,
                ],
                [
                    'is_active' => true,
                    'joined_at' => now()->toDateString(),
                    'left_at' => null,
                ]
            );
            if ($row->wasRecentlyCreated)
                $created++;
        }

        $this->command->info("Seeder SiswaKelas selesai. Ditambahkan: {$created} record.");
    }
}
