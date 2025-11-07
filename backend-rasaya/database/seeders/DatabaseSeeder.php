<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            UserSeeder::class,   // seeder punyamu yg sudah isi identifier & role
            TahunAjaranSeeder::class,
            KategoriMasalahSeeder::class,
            MasterKategoriMasalahSeeder::class,
            MasterKategoriPivotFromTaxonomySeeder::class,
            // MasterRekomendasiBulkSeeder::class,
            MasterRekomendasiPsychSeeder::class,
            MasterRekomendasiPivotSeeder::class,
                // SiswaKelasSeeder::class,            
            // SetupKelasXiiIps2Seeder::class,
            // SiswaXiiIps2Seeder::class,
            // RefleksiPribadiSeeder::class,
            // LaporanTemanSeeder::class,
            // PemantauanEmosiHarianSeeder::class,
            // ObservasiWaliSeeder::class,
            // ObservasiBkSeeder::class,
        ]);
    }
}
