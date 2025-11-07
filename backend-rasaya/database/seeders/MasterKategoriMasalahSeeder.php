<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterKategoriMasalah;

class MasterKategoriMasalahSeeder extends Seeder
{
    public function run(): void
    {
        // Top-level topics extracted from taxonomy.json (ids & names)
        $topics = [
            ['kode' => 'MENTAL_EMOSI', 'nama' => 'Kesehatan Mental & Emosi', 'deskripsi' => 'Topik besar terkait kesehatan mental dan regulasi emosi.', 'is_active' => true],
            ['kode' => 'SOSIAL_PERGAULAN', 'nama' => 'Sosial & Pergaulan', 'deskripsi' => 'Interaksi sosial, konflik teman sebaya, perundungan.', 'is_active' => true],
            ['kode' => 'KELUARGA_ASUH', 'nama' => 'Keluarga & Pola Asuh', 'deskripsi' => 'Relasi keluarga, tekanan atau dinamika rumah.', 'is_active' => true],
            ['kode' => 'AKADEMIS_DISIPLIN', 'nama' => 'Akademis & Disiplin', 'deskripsi' => 'Motivasi belajar, tugas, kedisiplinan sekolah.', 'is_active' => true],
            ['kode' => 'FISIK_GAYA_HIDUP', 'nama' => 'Kesehatan Fisik & Gaya Hidup', 'deskripsi' => 'Aktivitas fisik, tidur, gizi dan kebiasaan.', 'is_active' => true],
            ['kode' => 'RELASI_PERCINTAAN', 'nama' => 'Relasi & Percintaan', 'deskripsi' => 'Hubungan romantis dan dinamika emosionalnya.', 'is_active' => true],
            ['kode' => 'KARIER_MASA_DEPAN', 'nama' => 'Karier & Masa Depan', 'deskripsi' => 'Perencanaan jurusan, pendidikan lanjutan, ekonomi.', 'is_active' => true],
            ['kode' => 'TATA_TERTIB', 'nama' => 'Disiplin & Tata Tertib', 'deskripsi' => 'Kepatuhan terhadap aturan sekolah dan manajemen waktu.', 'is_active' => true],
            ['kode' => 'DIGITAL_WELLBEING', 'nama' => 'Digital Wellbeing', 'deskripsi' => 'Keseimbangan penggunaan media sosial dan game.', 'is_active' => true],
            ['kode' => 'KEAMANAN_KESELAMATAN', 'nama' => 'Keamanan & Keselamatan', 'deskripsi' => 'Isu kekerasan fisik/verbal dan keselamatan pribadi.', 'is_active' => true],
        ];

        foreach ($topics as $tp) {
            MasterKategoriMasalah::updateOrCreate(['kode' => $tp['kode']], $tp);
        }
    }
}
