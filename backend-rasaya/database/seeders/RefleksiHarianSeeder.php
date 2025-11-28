<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RefleksiHarianSeeder extends Seeder
{
    public function run()
    {
        // Bersihkan data refleksi pribadi saja
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('input_siswas')->where('is_friend', 0)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ==========================================
        // PERSONALITY TEXTS (SUPER VARIASI)
        // ==========================================

        $personalities = [
            1 => [ // Terlambat Kronis
                'texts' => [
                    'bangun kesiangan lagi, capek banget',
                    'ngantuk parah pas masuk kelas',
                    'lebih seger sore, pagi parah',
                    'telat lagi, males banget rasanya',
                    'malam tidur kemalaman gara-gara HP',
                    'dimarahin guru karena telat lagi',
                    'hari ini gak telat, lumayan senang',
                    'pagi berat banget, mata susah kebuka',
                    'kayaknya kurang tidur terus belakangan',
                    'sore lumayan enakan dibanding pagi',
                    'besok harus tidur cepat deh',
                    'ke sekolah buru-buru sampai keringetan',
                ]
            ],
            2 => [ // Broken Home
                'texts' => [
                    'rumah ribut lagi, bikin pusing',
                    'susah fokus karena kepikiran orang rumah',
                    'pagi murung tapi sore lumayan',
                    'gak tau kenapa sedih terus',
                    'kepikiran mama sama papa terus',
                    'agak tenang hari ini, bersyukur',
                    'bingung harus cerita ke siapa',
                    'sekolah agak mending daripada di rumah',
                    'kadang capek mental begini',
                    'pengen istirahat dari semua problem',
                    'mood naik turun dari tadi',
                    'malem nangis, pagi masih kebawa',
                    'denger mereka ribut bikin sakit kepala',
                    'gak bisa konsen belajar sama sekali',
                ]
            ],
            3 => [ // Introvert
                'texts' => [
                    'hari ini biasa banget',
                    'kelas aman, nothing special',
                    'lebih suka diem hari ini',
                    'semuanya normal',
                    'capek sedikit, tapi gapapa',
                    'gak banyak ngobrol hari ini',
                    'lebih nyaman kalau sendiri',
                    'kerja kelompok agak awkward',
                    'mood stabil aja',
                    'gak ada yang mengganggu',
                    'hari berjalan tenang',
                    'cuma pengen cepat pulang',
                ]
            ],
            4 => [ // Ekstrovert
                'texts' => [
                    'seru banget sama anak-anak',
                    'nongkrong abis kelas, fun!',
                    'ketawa mulu hari ini',
                    'hari ini rame banget, suka banget',
                    'kelas asik karena rame',
                    'ngobrol sama banyak teman',
                    'energi full banget hari ini',
                    'ketemu temen lama, seneng!',
                    'hari ini aktif banget rasanya',
                    'ada cerita lucu sepanjang pelajaran',
                    'kelas heboh tapi fun',
                    'teman-teman bikin hariku hidup',
                    'ada banyak obrolan seru hari ini',
                    'mood bagus banget kayaknya',
                ]
            ],
            5 => [ // Perfeksionis
                'texts' => [
                    'tugas numpuk banget, stress',
                    'materi ujian susah tapi coba kejar',
                    'takut nilai turun banget',
                    'gak puas sama hasil kerja',
                    'pagi optimis, sore kecapean',
                    'review materi sampai pusing',
                    'deadline bikin deg-degan',
                    'harus lebih rapi kerjaannya',
                    'target tinggi banget hari ini',
                    'susah fokus karena banyak yang dipikir',
                    'nilai tryout kurang, jadi kepikiran',
                    'perfeksionis banget jadinya capek sendiri',
                    'ngerjain PR detail banget biar sempurna',
                    'pengen hasil sempurna tapi berat',
                ]
            ],
            6 => [ // Pemalu
                'texts' => [
                    'deg-degan waktu disuruh maju',
                    'malu banget tadi pas ngomong',
                    'gak yakin sama jawaban sendiri',
                    'takut salah ngomong hari ini',
                    'lebih nyaman diem aja',
                    'presentasi bikin panik',
                    'agak minder lihat teman lain',
                    'gak pede banget hari ini',
                    'pengen ngomong tapi takut',
                    'bingung respon orang apa',
                    'lebih tenang kalau sendiri',
                    'tadi salah ngomong jadi malu',
                    'gak berani tanya guru',
                    'coba kuat tapi masih grogi',
                ]
            ],
            7 => [ // Overthinking
                'texts' => [
                    'kepikiran hal kecil seharian',
                    'mood naik turun parah banget',
                    'overthinking sampai pusing',
                    'takut salah langkah',
                    'pagi kacau, sore lumayan',
                    'kepikiran omongan orang terus',
                    'gak bisa stop mikir',
                    'khawatir hal-hal kecil banget',
                    'banyak worry dari tadi',
                    'merasa aneh tanpa alasan',
                    'kecapean mikir terus',
                    'banyak skenario negatif di kepala',
                    'mikir berlebihan bikin capek',
                    'kepikiran nilai, tugas, semuanya',
                    'takut hal jelek terjadi',
                    'pengen tenang tapi susah banget',
                ]
            ],
            8 => [ // Disiplin Buruk
                'texts' => [
                    'malas sekolah sumpah',
                    'bosen banget di kelas',
                    'telat lagi gapapa lah',
                    'pengen pulang cepat',
                    'hari ini males banget belajar',
                    'gak mood masuk kelas',
                    'ketiduran di kelas tadi',
                    'gabut banget seharian',
                    'guru ngomel terus, capek dengernya',
                    'ngumpulin tugas telat lagi',
                    'males bangun pagi',
                    'pengen libur aja',
                    'hari ini gak fokus sama sekali',
                    'ngantuk terus dari pagi',
                ]
            ],
            9 => [ // Helper
                'texts' => [
                    'senang bantu temanku yang sedih',
                    'hari ini lumayan produktif',
                    'bantuin kerja kelompok',
                    'temanku curhat, aku dengerin',
                    'merasa bermanfaat hari ini',
                    'bantu temen belajar',
                    'ngasih semangat ke temen yang down',
                    'ikut bantu rapikan kelas',
                    'ngobrol positif sama teman',
                    'mood bagus karena bantu orang',
                    'bikin tugas bareng seru',
                    'ngajak temen yang murung buat cerita',
                    'senang lihat temanku senyum lagi',
                    'ngerasa dihargai hari ini',
                    'harinya tenang dan baik',
                    'senang bisa menolong orang lain',
                ]
            ],
            10 => [ // Akademik Bermasalah
                'texts' => [
                    'pelajaran susah banget',
                    'gak ngerti materi matematika',
                    'ketinggalan kelas, pusing',
                    'belajar tapi tetep gak paham',
                    'nilai jelek bikin drop',
                    'susah fokus di kelas',
                    'guru jelasin cepet banget',
                    'teman udah paham aku belum',
                    'mikir keras tapi gak masuk',
                    'bingung banget di pelajaran inti',
                    'mati gaya pas ditanya guru',
                    'pengen nyerah tapi coba lagi',
                    'baca ulang materi tetep gak ngerti',
                    'butuh bantuan belajar banget',
                    'capek belajar sendirian',
                ]
            ],
        ];

        // ==========================================
        // Helper Insert Refleksi
        // ==========================================

        $insertRefleksi = function ($siswaId, $date, $texts) {
            DB::table('input_siswas')->insert([
                'siswa_kelas_id' => $siswaId,
                'siswa_dilapor_kelas_id' => null,
                'is_friend' => 0,
                'tanggal' => $date,
                'teks' => $texts[array_rand($texts)],
                'gambar' => null,
                'status_upload' => 1,
                'meta' => json_encode([
                    'src' => 'flutter',
                    'jenis' => 'pribadi'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        // ==========================================
        // (1) 7 HARI TERAKHIR
        // ==========================================

        foreach ($personalities as $siswaId => $p) {
            for ($i = 0; $i < 7; $i++) {
                $date = now()->subDays(6 - $i)->toDateString();
                $insertRefleksi($siswaId, $date, $p['texts']);
            }
        }

        // ==========================================
        // (2) 1 MINGGU SEBELUMNYA
        // ==========================================

        foreach ($personalities as $siswaId => $p) {
            for ($i = 8; $i <= 14; $i++) {
                $date = now()->subDays($i)->toDateString();
                $insertRefleksi($siswaId, $date, $p['texts']);
            }
        }

        // ==========================================
        // (3) 1 BULAN SEBELUMNYA
        // ==========================================

        foreach ($personalities as $siswaId => $p) {
            for ($i = 15; $i <= 44; $i++) {
                $date = now()->subDays($i)->toDateString();
                $insertRefleksi($siswaId, $date, $p['texts']);
            }
        }

        // ==========================================
        // (4) 1 TAHUN SEBELUMNYA
        // ==========================================

        foreach ($personalities as $siswaId => $p) {
            for ($i = 50; $i <= 415; $i++) {
                $date = now()->subDays($i)->toDateString();
                $insertRefleksi($siswaId, $date, $p['texts']);
            }
        }

        echo "Refleksi Harian Seeder (Variasi Lengkap) completed.\n";
    }
}
