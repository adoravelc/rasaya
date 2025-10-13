<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class KategoriMasalahSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        \App\Models\KategoriMasalah::upsert([
            ['kode' => 'AKD', 'nama' => 'Akademik', 'deskripsi' => 'Tugas/ujian, konsentrasi'],
            ['kode' => 'EMO', 'nama' => 'Emosi', 'deskripsi' => 'Mood, cemas, stres'],
            ['kode' => 'SOS', 'nama' => 'Sosial', 'deskripsi' => 'Teman, keluarga'],
            ['kode' => 'DIS', 'nama' => 'Disiplin', 'deskripsi' => 'Keterlambatan, aturan'],
        ], ['kode'], ['nama', 'deskripsi', 'is_active']);
    }
}
