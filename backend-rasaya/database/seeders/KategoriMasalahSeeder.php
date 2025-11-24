<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KategoriMasalah;

class KategoriMasalahSeeder extends Seeder
{
    public function run(): void
    {
        // Data disusun berdasarkan file Excel "Kategori Masalah Siswa SMA"
        // Kode disusun agar unik: [GROUP]_[SINGKATAN]
        // Contoh: KME_SA (Kesehatan Mental Emosi - Stres Akademik)

        $data = [
            // === GROUP: KESEHATAN MENTAL & EMOSI ===
            [
                'kode' => 'KME_SA',
                'nama' => 'Stres Akademik',
                'deskripsi' => 'Tekanan berlebih karena tugas/ujian/nilai yang memicu tegang, lelah, dan kesulitan fokus.'
            ],
            [
                'kode' => 'KME_KS',
                'nama' => 'Kecemasan Sosial',
                'deskripsi' => 'Rasa takut, malu, gugup saat tampil/berinteraksi sehingga menghindar dari partisipasi.'
            ],
            [
                'kode' => 'KME_DR',
                'nama' => 'Depresi Ringan',
                'deskripsi' => 'Perasaan sedih/kehilangan minat berkepanjangan, energi rendah, dan menarik diri.'
            ],
            [
                'kode' => 'KME_GT',
                'nama' => 'Gangguan Tidur',
                'deskripsi' => 'Kesulitan tidur/insomnia akibat stres atau paparan layar yang mengganggu fungsi siang hari.'
            ],

            // === GROUP: SOSIAL & PERGAULAN ===
            [
                'kode' => 'SOP_BTM',
                'nama' => 'Bullying Tatap Muka',
                'deskripsi' => 'Perundungan langsung: ejekan, eksklusi, atau kekerasan fisik di sekolah.'
            ],
            [
                'kode' => 'SOP_CB',
                'nama' => 'Cyberbullying',
                'deskripsi' => 'Perundungan digital melalui chat/grup/medsos: penghinaan, sebar foto, doxing.'
            ],
            [
                'kode' => 'SOP_TTS',
                'nama' => 'Tekanan Teman Sebaya',
                'deskripsi' => 'Dorongan mengikuti perilaku yang tidak diinginkan agar diterima kelompok.'
            ],
            [
                'kode' => 'SOP_KI',
                'nama' => 'Kesepian / Isolasi',
                'deskripsi' => 'Perasaan tidak punya teman dekat/dukungan sosial di sekolah.'
            ],

            // === GROUP: KELUARGA & POLA ASUH ===
            [
                'kode' => 'KPA_KOB',
                'nama' => 'Konflik Orang Tua / Broken Home',
                'deskripsi' => 'Konflik rumah tangga, perceraian, atau kekerasan verbal yang memicu distress siswa.'
            ],
            [
                'kode' => 'KPA_TPK',
                'nama' => 'Tekanan Prestasi Keluarga',
                'deskripsi' => 'Tuntutan nilai/peringkat yang tidak realistis hingga memicu stres dan takut gagal.'
            ],

            // === GROUP: AKADEMIS & DISIPLIN ===
            [
                'kode' => 'AKD_MBR',
                'nama' => 'Motivasi Belajar Rendah',
                'deskripsi' => 'Minat/energi belajar menurun, lebih memilih aktivitas non-akademik.'
            ],
            [
                'kode' => 'AKD_PT',
                'nama' => 'Prokrastinasi Tugas',
                'deskripsi' => 'Menunda pekerjaan hingga mendekati tenggat, memicu kualitas rendah & stres.'
            ],
            [
                'kode' => 'AKD_KB',
                'nama' => 'Ketidakhadiran / Bolos',
                'deskripsi' => 'Sering absen/telat tanpa alasan jelas yang mengganggu capaian belajar.'
            ],

            // === GROUP: KESEHATAN FISIK & GAYA HIDUP ===
            [
                'kode' => 'KFG_KAF',
                'nama' => 'Kurang Aktivitas Fisik',
                'deskripsi' => 'Aktivitas fisik rendah yang berdampak pada kebugaran, mood, dan fokus belajar.'
            ],
            [
                'kode' => 'KFG_PTG',
                'nama' => 'Pola Tidur & Gizi Buruk',
                'deskripsi' => 'Jam tidur pendek/berantakan dan kebiasaan makan tidak teratur yang mengganggu kinerja.'
            ],

            // === GROUP: RELASI & PERCINTAAN ===
            [
                'kode' => 'REP_KP',
                'nama' => 'Konflik Percintaan',
                'deskripsi' => 'Cemburu/curiga/selisih paham yang menurunkan fokus belajar & kestabilan emosi.'
            ],
            [
                'kode' => 'REP_PK',
                'nama' => 'Putus & Kehilangan',
                'deskripsi' => 'Kehilangan pasangan memicu sedih/menarik diri, mengganggu rutinitas sekolah.'
            ],

            // === GROUP: KARIER & MASA DEPAN ===
            [
                'kode' => 'KMD_KJK',
                'nama' => 'Kebingungan Jurusan / Karier',
                'deskripsi' => 'Kurang pemahaman diri/informasi sehingga ragu menentukan langkah pasca-SMA.'
            ],
            [
                'kode' => 'KMD_HE',
                'nama' => 'Hambatan Ekonomi',
                'deskripsi' => 'Keterbatasan biaya/dukungan finansial yang menghambat rencana studi lanjut.'
            ],

            // === GROUP: DISIPLIN & TATA TERTIB ===
            [
                'kode' => 'DTT_PTT',
                'nama' => 'Pelanggaran Tata Tertib',
                'deskripsi' => 'Melanggar aturan (seragam, rambut, gawai) karena resistensi atau ketidaktahuan.'
            ],
            [
                'kode' => 'DTT_MWB',
                'nama' => 'Manajemen Waktu Buruk',
                'deskripsi' => 'Kesulitan menyusun prioritas/jadwal sehingga kewalahan akademik & kegiatan.'
            ],

            // === GROUP: DIGITAL WELLBEING ===
            [
                'kode' => 'DGW_OMS',
                'nama' => 'Overuse Media Sosial',
                'deskripsi' => 'Penggunaan medsos berlebihan yang memicu distraksi, FOMO, dan mood negatif.'
            ],
            [
                'kode' => 'DGW_GB',
                'nama' => 'Game Berlebihan',
                'deskripsi' => 'Jam bermain gim tinggi mengganggu tidur, tugas, dan relasi keluarga.'
            ],

            // === GROUP: KEAMANAN & KESELAMATAN ===
            [
                'kode' => 'KKS_KFD',
                'nama' => 'Kekerasan Fisik / Verbal oleh Dewasa',
                'deskripsi' => 'Pengalaman kekerasan dari orang dewasa (termasuk guru) yang menimbulkan takut/trauma.'
            ],
            [
                'kode' => 'KKS_PBG',
                'nama' => 'Perundungan Berbasis Gender',
                'deskripsi' => 'Pelecehan stereotip/perilaku tidak pantas yang menarget identitas gender.'
            ],
        ];

        foreach ($data as $item) {
            KategoriMasalah::updateOrCreate(
                ['kode' => $item['kode']],
                [
                    'nama' => $item['nama'],
                    'deskripsi' => $item['deskripsi'],
                    'is_active' => true
                ]
            );
        }
    }
}