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
            // Akademik & Disiplin
            ['kode' => 'AKD', 'nama' => 'Akademik', 'deskripsi' => 'Stres akademik, motivasi belajar, prokrastinasi, ketidakhadiran', 'is_active' => true],
            ['kode' => 'DIS', 'nama' => 'Disiplin & Tata Tertib', 'deskripsi' => 'Pelanggaran aturan, keterlambatan, manajemen waktu buruk', 'is_active' => true],

            // Mental & Emosi
            ['kode' => 'EMO', 'nama' => 'Kesehatan Mental & Emosi', 'deskripsi' => 'Depresi ringan, kecemasan sosial, gangguan tidur, regulasi emosi', 'is_active' => true],

            // Sosial & Keluarga
            ['kode' => 'SOS', 'nama' => 'Sosial & Pergaulan', 'deskripsi' => 'Bullying, cyberbullying, tekanan teman sebaya, kesepian', 'is_active' => true],
            ['kode' => 'KEL', 'nama' => 'Keluarga & Pola Asuh', 'deskripsi' => 'Konflik orang tua, broken home, tekanan prestasi keluarga', 'is_active' => true],

            // Fisik & Gaya Hidup
            ['kode' => 'FIS', 'nama' => 'Kesehatan Fisik & Gaya Hidup', 'deskripsi' => 'Kurang aktivitas fisik, pola tidur & gizi buruk', 'is_active' => true],

            // Relasi & Karier
            ['kode' => 'REL', 'nama' => 'Relasi & Percintaan', 'deskripsi' => 'Konflik percintaan, putus & kehilangan', 'is_active' => true],
            ['kode' => 'KAR', 'nama' => 'Karier & Masa Depan', 'deskripsi' => 'Kebingungan jurusan/karier, hambatan ekonomi', 'is_active' => true],

            // Digital & Keamanan
            ['kode' => 'DWB', 'nama' => 'Digital Wellbeing', 'deskripsi' => 'Overuse media sosial, game berlebihan', 'is_active' => true],
            ['kode' => 'KAM', 'nama' => 'Keamanan & Keselamatan', 'deskripsi' => 'Kekerasan fisik/verbal oleh dewasa, perundungan berbasis gender', 'is_active' => true],
        ], ['kode'], ['nama', 'deskripsi', 'is_active']);
    }
}
