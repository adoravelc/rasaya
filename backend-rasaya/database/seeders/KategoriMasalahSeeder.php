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
            ['kode' => 'SAKD', 'nama' => 'Stres Akademik', 'deskripsi' => 'Tekanan akademik, beban belajar tinggi, dan stres karena tugas sekolah.', 'is_active' => true],
            ['kode' => 'KSOS', 'nama' => 'Kecemasan Sosial', 'deskripsi' => 'Rasa takut atau canggung saat berinteraksi sosial di lingkungan sekolah.', 'is_active' => true],
            ['kode' => 'DPRN', 'nama' => 'Depresi Ringan', 'deskripsi' => 'Perasaan sedih berkepanjangan, kehilangan minat, atau semangat belajar.', 'is_active' => true],
            ['kode' => 'GTDR', 'nama' => 'Gangguan Tidur', 'deskripsi' => 'Kesulitan tidur, insomnia, atau tidur berlebihan.', 'is_active' => true],
            ['kode' => 'BTMK', 'nama' => 'Bullying Tatap Muka', 'deskripsi' => 'Perundungan secara langsung seperti ejekan, dorongan, atau pengucilan.', 'is_active' => true],
            ['kode' => 'CBUL', 'nama' => 'Cyberbullying', 'deskripsi' => 'Perundungan di dunia maya melalui media sosial atau chat.', 'is_active' => true],
            ['kode' => 'TTSP', 'nama' => 'Tekanan Teman Sebaya', 'deskripsi' => 'Dorongan negatif dari teman sebaya untuk melakukan hal tertentu.', 'is_active' => true],
            ['kode' => 'KISO', 'nama' => 'Kesepian / Isolasi', 'deskripsi' => 'Perasaan terasing atau tidak memiliki teman dekat.', 'is_active' => true],
            ['kode' => 'KOTH', 'nama' => 'Konflik Orang Tua / Broken Home', 'deskripsi' => 'Masalah dalam keluarga, perceraian, atau hubungan orang tua yang tidak harmonis.', 'is_active' => true],
            ['kode' => 'TPKL', 'nama' => 'Tekanan Prestasi Keluarga', 'deskripsi' => 'Tuntutan tinggi dari keluarga untuk berprestasi di bidang akademik.', 'is_active' => true],
            ['kode' => 'MBRD', 'nama' => 'Motivasi Belajar Rendah', 'deskripsi' => 'Kurangnya semangat atau minat dalam kegiatan belajar.', 'is_active' => true],
            ['kode' => 'PRTG', 'nama' => 'Prokrastinasi Tugas', 'deskripsi' => 'Kebiasaan menunda pekerjaan atau tugas sekolah.', 'is_active' => true],
            ['kode' => 'KBLN', 'nama' => 'Ketidakhadiran / Bolos', 'deskripsi' => 'Sering absen atau menghindari kegiatan sekolah.', 'is_active' => true],
            ['kode' => 'KAFK', 'nama' => 'Kurang Aktivitas Fisik', 'deskripsi' => 'Jarang berolahraga atau bergerak secara aktif.', 'is_active' => true],
            ['kode' => 'PTGB', 'nama' => 'Pola Tidur & Gizi Buruk', 'deskripsi' => 'Tidur tidak teratur dan kebiasaan makan yang tidak sehat.', 'is_active' => true],
            ['kode' => 'KPCR', 'nama' => 'Konflik Percintaan', 'deskripsi' => 'Pertengkaran atau masalah dalam hubungan romantis.', 'is_active' => true],
            ['kode' => 'PTKH', 'nama' => 'Putus & Kehilangan', 'deskripsi' => 'Kesedihan akibat putus cinta atau kehilangan orang terdekat.', 'is_active' => true],
            ['kode' => 'KJUR', 'nama' => 'Kebingungan Jurusan / Karier', 'deskripsi' => 'Kebingungan menentukan jurusan kuliah atau arah karier masa depan.', 'is_active' => true],
            ['kode' => 'HEKO', 'nama' => 'Hambatan Ekonomi', 'deskripsi' => 'Kesulitan finansial yang menghambat kegiatan belajar.', 'is_active' => true],
            ['kode' => 'PTTB', 'nama' => 'Pelanggaran Tata Tertib', 'deskripsi' => 'Melanggar aturan sekolah seperti keterlambatan atau perilaku tidak sopan.', 'is_active' => true],
            ['kode' => 'MWBK', 'nama' => 'Manajemen Waktu Buruk', 'deskripsi' => 'Kesulitan mengatur waktu antara belajar dan aktivitas lain.', 'is_active' => true],
            ['kode' => 'OMSO', 'nama' => 'Overuse Media Sosial', 'deskripsi' => 'Penggunaan media sosial secara berlebihan hingga mengganggu aktivitas.', 'is_active' => true],
            ['kode' => 'GBRL', 'nama' => 'Game Berlebihan', 'deskripsi' => 'Kecanduan bermain game hingga mengganggu belajar dan kehidupan sosial.', 'is_active' => true],
            ['kode' => 'KFVD', 'nama' => 'Kekerasan Fisik / Verbal oleh Dewasa', 'deskripsi' => 'Kekerasan atau perlakuan kasar dari orang dewasa di sekitar.', 'is_active' => true],
            ['kode' => 'PBGD', 'nama' => 'Perundungan Berbasis Gender', 'deskripsi' => 'Diskriminasi atau perundungan karena identitas atau ekspresi gender.', 'is_active' => true],
        ], ['kode'], ['nama', 'deskripsi', 'is_active']);
    }
}
