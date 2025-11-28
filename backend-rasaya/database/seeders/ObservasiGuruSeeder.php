<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ObservasiGuruSeeder extends Seeder
{
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('input_gurus')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // GURU
        $guruBK = 2;
        $waliKelas = [
            1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3,
            6 => 7, 7 => 7, 8 => 7,
            9 => 10, 10 => 10
        ];

        // PERSONALITY MAPPING (FINAL)
        $p = [
            1 => [
                'kategori' => 8,
                'warna' => ['yellow','orange'],
                'bk_texts' => [
                    'Siswa sering datang terlambat dan tampak kurang tidur.',
                    'Perlu bimbingan manajemen waktu, terlihat lelah di pagi hari.',
                    'Konsentrasi rendah pada jam pertama akibat pola tidur buruk.'
                ],
                'wk_texts' => [
                    'Terlambat masuk kelas pagi ini.',
                    'Mengantuk berat saat pelajaran pertama.',
                    'Masuk kelas dalam kondisi terburu-buru.'
                ]
            ],
            2 => [
                'kategori' => 3,
                'warna' => ['orange','red'],
                'bk_texts' => [
                    'Siswa tampak murung karena konflik keluarga.',
                    'Emosi tidak stabil dan sulit fokus.',
                    'Membutuhkan sesi konseling lanjutan.'
                ],
                'wk_texts' => [
                    'Terlihat tidak fokus dan menyendiri.',
                    'Sering termenung saat pelajaran.',
                    'Kurang berpartisipasi di kelas.'
                ]
            ],
            3 => [
                'kategori' => 2,
                'warna' => ['green','yellow'],
                'bk_texts' => [
                    'Siswa pendiam namun stabil secara emosional.',
                    'Kurang percaya diri dalam interaksi kelas.',
                    'Lebih nyaman bekerja sendiri.'
                ],
                'wk_texts' => [
                    'Pasif namun memperhatikan pelajaran.',
                    'Tidak banyak berbicara di kelas.',
                    'Partisipasi minim tetapi baik.'
                ]
            ],
            4 => [
                'kategori' => 2,
                'warna' => ['green','yellow'],
                'bk_texts' => [
                    'Siswa sangat aktif berinteraksi.',
                    'Energi sosial tinggi dan ekspresif.',
                    'Perlu diarahkan agar tidak mendominasi.'
                ],
                'wk_texts' => [
                    'Banyak bicara saat pelajaran.',
                    'Membuat suasana kelas hidup.',
                    'Kadang terlalu heboh.'
                ]
            ],
            5 => [
                'kategori' => 4,
                'warna' => ['yellow','orange'],
                'bk_texts' => [
                    'Menunjukkan kecemasan berlebih terkait tugas.',
                    'Perfeksionisme mempengaruhi emosinya.',
                    'Tampak tertekan saat ujian.'
                ],
                'wk_texts' => [
                    'Terlalu teliti sehingga lambat.',
                    'Tampak stres memikirkan tugas.',
                    'Mengulang pekerjaan berkali-kali.'
                ]
            ],
            6 => [
                'kategori' => 2,
                'warna' => ['yellow','green'],
                'bk_texts' => [
                    'Canggung dan gugup saat berbicara.',
                    'Kurang percaya diri dalam kelompok.',
                    'Membutuhkan dukungan lebih.'
                ],
                'wk_texts' => [
                    'Bicara sangat pelan saat presentasi.',
                    'Tidak berani menjawab meski tahu.',
                    'Canggung dalam kerja kelompok.'
                ]
            ],
            7 => [
                'kategori' => 1,
                'warna' => ['orange','red'],
                'bk_texts' => [
                    'Overthinking mengganggu proses belajar.',
                    'Sering cemas dan sulit fokus.',
                    'Terlalu banyak pikiran.'
                ],
                'wk_texts' => [
                    'Sering melamun saat kelas.',
                    'Sulit memahami instruksi sederhana.',
                    'Tampak bingung sepanjang pelajaran.'
                ]
            ],
            8 => [
                'kategori' => 8,
                'warna' => ['orange','red'],
                'bk_texts' => [
                    'Motivasi belajar rendah.',
                    'Tidak konsisten dalam tugas.',
                    'Sering mengabaikan arahan.'
                ],
                'wk_texts' => [
                    'Tidak mengerjakan tugas tepat waktu.',
                    'Kurang fokus dan memainkan HP.',
                    'Masuk kelas terlambat tanpa alasan.'
                ]
            ],
            9 => [
                'kategori' => 2,
                'warna' => ['green','yellow'],
                'bk_texts' => [
                    'Siswa memiliki empati tinggi.',
                    'Emosi stabil dan kooperatif.',
                    'Sering membantu teman.'
                ],
                'wk_texts' => [
                    'Aktif membantu saat kerja kelompok.',
                    'Menjadi penyeimbang suasana kelas.',
                    'Mudah diajak bekerja sama.'
                ]
            ],
            10 => [
                'kategori' => 4,
                'warna' => ['orange','red'],
                'bk_texts' => [
                    'Kesulitan memahami materi.',
                    'Membutuhkan remedial berulang.',
                    'Tampak frustrasi saat belajar.'
                ],
                'wk_texts' => [
                    'Tertinggal dalam latihan kelas.',
                    'Sering tidak paham instruksi.',
                    'Butuh bantuan tambahan dalam belajar.'
                ]
            ],
        ];

        // Helper insert
        $insertObs = function ($guruId, $siswaId, $date, $persona, $isBK = false) {
            DB::table('input_gurus')->insert([
                'guru_id' => $guruId,
                'siswa_kelas_id' => $siswaId,
                'master_kategori_masalah_id' => $persona['kategori'],
                'tanggal' => $date,
                'teks' => ($isBK
                    ? $persona['bk_texts'][array_rand($persona['bk_texts'])]
                    : $persona['wk_texts'][array_rand($persona['wk_texts'])]),
                'gambar' => null,
                'kondisi_siswa' => $persona['warna'][array_rand($persona['warna'])],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        // Generate Observasi
        foreach ($p as $siswaId => $persona) {

            // 1× per minggu → BK
            foreach ([1, 8, 20, 100] as $offset) {
                $insertObs(2, $siswaId, now()->subDays($offset)->toDateString(), $persona, true);
            }

            // 2× per minggu → WK
            $wali = $waliKelas[$siswaId];
            foreach ([2, 5, 10, 12, 25, 30, 120, 150] as $offset) {
                $insertObs($wali, $siswaId, now()->subDays($offset)->toDateString(), $persona, false);
            }
        }

        echo "Observasi Guru BK & Wali Kelas Seeder COMPLETED.\n";
    }
}
