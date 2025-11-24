<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterRekomendasi;
use App\Models\KategoriMasalah;

class MasterRekomendasiCompleteSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ambil ID Kategori berdasarkan Kode Unik (Biar Relasinya Pasti Benar)
        $cats = KategoriMasalah::pluck('id', 'kode')->toArray();
        $getId = fn($code) => $cats[$code] ?? null;

        $data = [
            // ==================== KESEHATAN MENTAL & EMOSI ====================
            [
                'target_cat_kode' => 'KME_SA', // Stres Akademik
                'judul' => 'Konseling Individu Manajemen Stres',
                'deskripsi' => 'Membantu siswa mengenali pemicu stres (tugas/ujian) dan melatih teknik regulasi emosi serta relaksasi.',
                'severity' => 'medium',
                'keywords' => ['tugas', 'ujian', 'nilai', 'deadline', 'pusing', 'meledak', 'panik'],
            ],
            [
                'target_cat_kode' => 'KME_SA',
                'judul' => 'Pelatihan Manajemen Waktu & Belajar',
                'deskripsi' => 'Mengajarkan teknik time blocking & strategi belajar efektif sesuai gaya belajar siswa.',
                'severity' => 'low',
                'keywords' => ['belajar', 'masuk otak', 'deadline', 'nempel', 'capek'],
            ],
            [
                'target_cat_kode' => 'KME_KS', // Kecemasan Sosial
                'judul' => 'Latihan Role-Play & Public Speaking',
                'deskripsi' => 'Simulasi aman di ruang konseling untuk meningkatkan paparan sosial bertahap dan teknik relaksasi.',
                'severity' => 'medium',
                'keywords' => ['takut', 'malu', 'gugup', 'presentasi', 'diam', 'blank', 'keringet dingin'],
            ],
            [
                'target_cat_kode' => 'KME_DR', // Depresi Ringan
                'judul' => 'Skrining Awal & Konseling Suportif',
                'deskripsi' => 'Deteksi dini gejala depresi, memberikan dukungan emosional, dan aktivitas journaling (buku harian).',
                'severity' => 'high',
                'keywords' => ['sedih', 'kosong', 'nggak semangat', 'nangis', 'menarik diri', 'berat', 'males ketemu'],
            ],
            [
                'target_cat_kode' => 'KME_DR',
                'judul' => 'Aktivasi Rutinitas Harian Sehat',
                'deskripsi' => 'Pendampingan menstabilkan ritme tidur dan aktivitas fisik ringan untuk memperbaiki mood secara alami.',
                'severity' => 'medium',
                'keywords' => ['bangun pagi', 'berat', 'kosong', 'nangis'],
            ],
            [
                'target_cat_kode' => 'KME_GT', // Gangguan Tidur
                'judul' => 'Edukasi Sleep Hygiene & Batas Layar',
                'deskripsi' => 'Edukasi mengurangi faktor pengganggu tidur (gadget) dan membangun rutinitas relaksasi sebelum tidur.',
                'severity' => 'medium',
                'keywords' => ['begadang', 'insomnia', 'ngantuk', 'layar', 'kepikiran', 'scroll', 'subuh'],
            ],

            // ==================== SOSIAL & PERGAULAN ====================
            [
                'target_cat_kode' => 'SOP_BTM', // Bullying Tatap Muka
                'judul' => 'Program Anti-Bullying (Roots)',
                'deskripsi' => 'Intervensi perubahan norma sekolah melalui agen sebaya dan konseling pemulihan bagi korban.',
                'severity' => 'high',
                'keywords' => ['bully', 'ejek', 'eksklusi', 'dorong', 'gibah', 'dijauhin', 'geng'],
            ],
            [
                'target_cat_kode' => 'SOP_BTM',
                'judul' => 'Mediasi Restoratif (Jika Aman)',
                'deskripsi' => 'Memulihkan relasi dan tanggung jawab pelaku dengan pengawasan ketat, fokus pada pemulihan korban.',
                'severity' => 'high',
                'keywords' => ['ejek', 'fisik', 'sembunyiin', 'tas'],
            ],
            [
                'target_cat_kode' => 'SOP_CB', // Cyberbullying
                'judul' => 'Prosedur Bukti Digital & Takedown',
                'deskripsi' => 'Bantuan teknis menangani konten merugikan (report/block) dan konseling dampak psikologis.',
                'severity' => 'high',
                'keywords' => ['grup', 'sebar', 'hina', 'akun palsu', 'dm', 'ancaman', 'foto'],
            ],
            [
                'target_cat_kode' => 'SOP_TTS', // Tekanan Teman Sebaya
                'judul' => 'Pelatihan Asertif (Menolak Sehat)',
                'deskripsi' => 'Melatih siswa berkata "Tidak" tanpa merusak pertemanan dan membangun kepercayaan diri.',
                'severity' => 'medium',
                'keywords' => ['tekanan', 'ikut-ikutan', 'keren', 'solid', 'nolak', 'rokok', 'nongkrong'],
            ],
            [
                'target_cat_kode' => 'SOP_KI', // Kesepian / Isolasi
                'judul' => 'Mentoring Sebaya (Buddy System)',
                'deskripsi' => 'Memasangkan siswa dengan mentor sebaya atau teman pendamping untuk memberikan rasa aman awal.',
                'severity' => 'medium',
                'keywords' => ['kesepian', 'sendirian', 'dukungan', 'nggak diterima', 'dianggap', 'cerita'],
            ],

            // ==================== KELUARGA & POLA ASUH ====================
            [
                'target_cat_kode' => 'KPA_KOB', // Konflik Ortu / Broken Home
                'judul' => 'Konseling Keluarga Terarah',
                'deskripsi' => 'Fasilitasi komunikasi orang tua-anak untuk memperbaiki batas dan dukungan emosional di rumah.',
                'severity' => 'high',
                'keywords' => ['ribut', 'pisah', 'nggak tenang', 'takut pulang', 'kabur', 'cerai'],
            ],
            [
                'target_cat_kode' => 'KPA_TPK', // Tekanan Prestasi Keluarga
                'judul' => 'Edukasi Parenting: Dukungan vs Tuntutan',
                'deskripsi' => 'Diskusi dengan orang tua untuk menggeser pola asuh dari tuntutan nilai ke dukungan proses belajar.',
                'severity' => 'medium',
                'keywords' => ['dituntut', 'ranking', 'dibandingkan', 'marah', 'takut gagal', 'nilai jelek'],
            ],

            // ==================== AKADEMIS & DISIPLIN ====================
            [
                'target_cat_kode' => 'AKD_MBR', // Motivasi Belajar Rendah
                'judul' => 'Konseling Motivasi (Goal Setting)',
                'deskripsi' => 'Menetapkan tujuan pribadi yang bermakna dan mengaitkannya dengan minat/gaya belajar siswa.',
                'severity' => 'low',
                'keywords' => ['males', 'nggak minat', 'capek', 'scroll', 'nggak guna', 'berat', 'buka buku'],
            ],
            [
                'target_cat_kode' => 'AKD_PT', // Prokrastinasi
                'judul' => 'Pelatihan Teknik Pomodoro & Segmentasi',
                'deskripsi' => 'Mengajarkan cara memecah tugas besar menjadi bagian kecil agar terasa ringan dan memulai dengan jeda terstruktur.',
                'severity' => 'medium',
                'keywords' => ['nunda', 'deadline', 'perfeksionis', 'subuh', 'nanti', 'mepet'],
            ],
            [
                'target_cat_kode' => 'AKD_KB', // Bolos
                'judul' => 'Home Visit & Analisis Akar Masalah',
                'deskripsi' => 'Kunjungan rumah untuk memahami faktor penyebab bolos (keluarga/lingkungan) dan pendekatan disiplin positif.',
                'severity' => 'high',
                'keywords' => ['bolos', 'telat', 'mager', 'cabut', 'ijin', 'tidur pagi'],
            ],

            // ==================== FISIK & GAYA HIDUP ====================
            [
                'target_cat_kode' => 'KFG_KAF', // Kurang Fisik
                'judul' => 'Program Aktivitas Fisik Ringan',
                'deskripsi' => 'Mendorong partisipasi di klub olahraga rekreatif atau aktivitas fisik sederhana untuk meningkatkan mood.',
                'severity' => 'low',
                'keywords' => ['olahraga', 'ngos-ngosan', 'duduk', 'mager', 'kebugaran', 'gerak'],
            ],
            [
                'target_cat_kode' => 'KFG_PTG', // Pola Tidur & Gizi
                'judul' => 'Edukasi Gizi & Sarapan Sehat',
                'deskripsi' => 'Penyuluhan pentingnya sarapan dan gizi seimbang untuk konsentrasi belajar di kelas.',
                'severity' => 'low',
                'keywords' => ['skip sarapan', 'kurang tidur', 'lemas', 'kopi', 'diet', 'melek'],
            ],

            // ==================== RELASI & PERCINTAAN ====================
            [
                'target_cat_kode' => 'REP_KP', // Konflik Percintaan
                'judul' => 'Konseling Komunikasi & Batasan Sehat',
                'deskripsi' => 'Melatih komunikasi asertif dalam hubungan dan menetapkan batasan (boundaries) yang realistis.',
                'severity' => 'medium',
                'keywords' => ['cemburu', 'curiga', 'berantem', 'ghosting', 'posesif', 'overthinking'],
            ],
            [
                'target_cat_kode' => 'REP_PK', // Putus Cinta
                'judul' => 'Konseling Dukacita Remaja',
                'deskripsi' => 'Memvalidasi emosi sedih/kehilangan dan menyediakan aktivitas pemulihan diri (katarsis) yang sehat.',
                'severity' => 'medium',
                'keywords' => ['putus', 'sedih', 'nangis', 'nggak nafsu', 'menarik diri', 'move on'],
            ],

            // ==================== KARIER & MASA DEPAN ====================
            [
                'target_cat_kode' => 'KMD_KJK', // Bingung Jurusan
                'judul' => 'Tes Minat Bakat & Interpretasi',
                'deskripsi' => 'Pelaksanaan tes psikologi untuk memetakan profil diri objektif dan sesi interpretasi hasil.',
                'severity' => 'low',
                'keywords' => ['kuliah', 'bingung', 'minat', 'bakat', 'takut salah', 'kerja'],
            ],
            [
                'target_cat_kode' => 'KMD_HE', // Hambatan Ekonomi
                'judul' => 'Klinik Beasiswa & Literasi Finansial',
                'deskripsi' => 'Bimbingan strategi mendaftar beasiswa (KIP-K/Swasta) dan info peluang kerja paruh waktu ramah pelajar.',
                'severity' => 'high',
                'keywords' => ['spp', 'biaya', 'beasiswa', 'kerja', 'mahal', 'ngos-ngosan'],
            ],

            // ==================== DISIPLIN ====================
            [
                'target_cat_kode' => 'DTT_PTT', // Langgar Tata Tertib
                'judul' => 'Disiplin Positif & Restoratif',
                'deskripsi' => 'Pendekatan disiplin yang fokus pada pemahaman aturan, tanggung jawab, dan pemulihan, bukan hukuman fisik.',
                'severity' => 'medium',
                'keywords' => ['poin', 'seragam', 'hp', 'sepatu', 'telat', 'aturan'],
            ],
            [
                'target_cat_kode' => 'DTT_MWB', // Manajemen Waktu Buruk
                'judul' => 'Workshop Perencanaan Mingguan',
                'deskripsi' => 'Mengajari siswa membuat kalender prioritas dan cek-in berkala dengan wali kelas untuk monitoring.',
                'severity' => 'low',
                'keywords' => ['prioritas', 'jadwal', 'bentrok', 'lupa', 'numpuk', 'mepet'],
            ],

            // ==================== DIGITAL ====================
            [
                'target_cat_kode' => 'DGW_OMS', // Overuse Medsos
                'judul' => 'Detoks Digital Bertahap',
                'deskripsi' => 'Strategi penurunan durasi layar secara bertahap dan edukasi literasi media (kritisi konten/FOMO).',
                'severity' => 'medium',
                'keywords' => ['scroll', 'fomo', 'komen', 'bandingin', 'kacau tidur', 'story'],
            ],
            [
                'target_cat_kode' => 'DGW_GB', // Game Berlebihan
                'judul' => 'Kontrak Waktu Bermain Sehat',
                'deskripsi' => 'Kesepakatan tertulis batas waktu bermain gim dengan keterlibatan orang tua untuk pengawasan konsisten.',
                'severity' => 'high',
                'keywords' => ['game', 'rank', 'subuh', 'marah', 'gelisah', 'top up'],
            ],

            // ==================== KEAMANAN ====================
            [
                'target_cat_kode' => 'KKS_KFD', // Kekerasan Dewasa
                'judul' => 'Mekanisme Aduan Aman & Pendampingan',
                'deskripsi' => 'Menyediakan kanal pelaporan rahasia dan pendampingan intensif oleh BK untuk memulihkan rasa aman.',
                'severity' => 'high',
                'keywords' => ['dibentak', 'ditampar', 'dipermalukan', 'takut', 'deg-degan', 'guru'],
            ],
            [
                'target_cat_kode' => 'KKS_PBG', // Perundungan Gender
                'judul' => 'Edukasi Kesetaraan & Hormat',
                'deskripsi' => 'Sosialisasi aturan anti-pelecehan dan nilai penghormatan terhadap identitas gender.',
                'severity' => 'high',
                'keywords' => ['gender', 'pelecehan', 'stereotip', 'catcalling', 'objek', 'banci', 'tomboy'],
            ],
        ];

        foreach ($data as $idx => $row) {
            // Generate Kode Rekomendasi Unik: REC_[KODE_KAT]_[INDEX]
            $uniqueCode = 'REC_' . $row['target_cat_kode'] . '_' . ($idx + 1);

            $rekom = MasterRekomendasi::updateOrCreate(
                ['kode' => $uniqueCode],
                [
                    'judul' => $row['judul'],
                    'deskripsi' => $row['deskripsi'],
                    'severity' => $row['severity'],
                    'is_active' => true,
                    // Simpan keywords & min_score di JSON
                    'rules' => [
                        'min_neg_score' => -0.15, // Default threshold agar gampang muncul
                        'any_keywords' => $row['keywords']
                    ]
                ]
            );

            // ATTACH RELASI
            $catId = $getId($row['target_cat_kode']);
            if ($catId) {
                $rekom->kategoris()->syncWithoutDetaching([$catId]);
            } else {
                $this->command->error("Kategori {$row['target_cat_kode']} tidak ditemukan!");
            }
        }
    }
}