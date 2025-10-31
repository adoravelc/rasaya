<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SetupKelasXiiIps2Seeder extends Seeder
{
    use ConciseSeederHelpers;

    public function run(): void
    {
        $taId  = $this->ensureTahunAjaran();
        $jurId = $this->findJurusanIPS();
        $wali  = $this->ensureGuru('David','wali_kelas');
        $this->ensureGuru('Natalia','bk'); // dipakai seeder BK
        $this->ensureKelas($taId, 'XII', 2, $jurId, $wali);
    }
}
