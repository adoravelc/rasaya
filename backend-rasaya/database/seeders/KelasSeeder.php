<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\Jurusan;

class KelasSeeder extends Seeder
{
    public function run(): void
    {
        $ta = TahunAjaran::query()->latest('id')->first();

        if (!$ta) {
            $this->command->warn('TahunAjaran belum ada. Seed tahun_ajarans dulu.');
            return;
        }

        // Pastikan ada beberapa jurusan contoh untuk TA ini
        $defaultJurs = ['IPA', 'IPS', 'Bahasa'];
        $jurusanMap = [null => null];
        foreach ($defaultJurs as $n) {
            $j = Jurusan::firstOrCreate(
                ['tahun_ajaran_id' => $ta->id, 'nama' => $n],
                []
            );
            $jurusanMap[$n] = $j->id;
        }

        // konfigurasi kelas yang ingin dibuat (tingkat, jurusan, rombel)
        $penjurusans = [null, 'IPA', 'IPS', 'Bahasa'];
        $rombelRange = range(1, 3); // rombel 1..3, ubah sesuai kebutuhan
        $created = 0;

        foreach (['X', 'XI', 'XII'] as $tingkat) {
            foreach ($penjurusans as $penjurusan) {
                foreach ($rombelRange as $rombel) {
                    $attrs = [
                        'tahun_ajaran_id' => $ta->id,
                        'tingkat' => $tingkat,
                        'jurusan_id' => $penjurusan ? $jurusanMap[$penjurusan] : null,
                        'rombel' => $rombel,
                    ];

                    // unique constraint di migration memastikan tidak duplikasi
                    $row = Kelas::firstOrCreate($attrs, [
                        'wali_guru_id' => null,
                    ]);

                    if ($row->wasRecentlyCreated)
                        $created++;
                }
            }
        }

        $this->command->info("KelasSeeder: selesai. Ditambahkan {$created} kelas (atau sudah ada).");
    }
}