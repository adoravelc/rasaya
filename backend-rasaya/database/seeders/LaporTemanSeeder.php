<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LaporTemanSeeder extends Seeder
{
    public function run()
    {
        // Bersihkan hanya data laporan (is_friend = 1)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('input_siswas')->where('is_friend', 1)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ==========================================
        // PERSONA → TARGET TEMAN → TEKS LAPORAN
        // ==========================================

        $mapping = [
            1 => [ // Telat kronis
                'targets' => [2,3],
                'texts' => [
                    'dia telat juga hari ini, sama kayak aku',
                    'temanku keliatan ngantuk berat',
                    'doa masuk kelas buru-buru banget',
                    'kayaknya dia kurang tidur juga',
                    'dia hampir telat tadi, lari-lari',
                    'dia ketiduran pas pelajaran pertama',
                ]
            ],
            2 => [ // Broken home
                'targets' => [1,5],
                'texts' => [
                    'dia keliatan murung dari tadi pagi',
                    'kayaknya habis nangis, matanya merah',
                    'dia duduk menyendiri pas istirahat',
                    'lagi banyak pikiran kayaknya',
                    'dia keliatan sedih pas denger guru ngomong',
                    'dia diam saja, tidak seperti biasanya',
                ]
            ],
            3 => [ // Introvert
                'targets' => [4,1],
                'texts' => [
                    'dia rame banget hari ini',
                    'temanku agak heboh di kelas',
                    'dia telat dikit, tapi santai',
                    'dia cukup aktif tadi',
                    'temanku cerita banyak tapi aku cuma dengerin',
                ]
            ],
            4 => [ // Ekstrovert
                'targets' => [3,8],
                'texts' => [
                    'dia pendiem banget hari ini, jarang ngomong',
                    'dia keliatan capek tapi tetap masuk',
                    'temanku keliatan kurang semangat',
                    'wah diem banget padahal biasanya rame',
                    'dia gak ikut ngobrol, mungkin capek',
                ]
            ],
            5 => [ // Perfeksionis
                'targets' => [10,2],
                'texts' => [
                    'dia belum kerjain tugas kelompok',
                    'temanku kesulitan materi tadi',
                    'dia bilang bingung sama PR',
                    'dia tidak siap presentasi hari ini',
                    'dia kurang fokus pas belajar kelompok',
                ]
            ],
            6 => [ // Pemalu
                'targets' => [9,2],
                'texts' => [
                    'dia keliatan sedih tapi aku gak berani tanya',
                    'temanku diam terus dari tadi',
                    'dia kayaknya gak pede pas pelajaran tadi',
                    'dia keliatan gugup banget',
                    'dia duduk sendirian lagi hari ini',
                ]
            ],
            7 => [ // Overthinking
                'targets' => [6,3],
                'texts' => [
                    'dia keliatan gelisah banget tadi',
                    'temanku kayak banyak pikiran',
                    'dia kayak cemas tapi gak bilang apa-apa',
                    'dia keliatan bingung sepanjang kelas',
                    'kayaknya dia lagi ada masalah',
                    'dia agak sensitif hari ini',
                ]
            ],
            8 => [ // Disiplin buruk
                'targets' => [10,4],
                'texts' => [
                    'dia bolos sebentar tadi',
                    'temanku ketiduran pas kelas',
                    'dia males banget belajar',
                    'dia ngomel-ngomel terus dari tadi',
                    'dia telat masuk kelas lagi',
                ]
            ],
            9 => [ // Helper
                'targets' => [2,6],
                'texts' => [
                    'dia kayaknya butuh bantuan, keliatan lelah',
                    'temanku murung, aku coba temenin',
                    'dia keliatan sedih jadi aku ajak ngobrol',
                    'dia bingung pas belajar, aku bantu sedikit',
                    'dia keliatan kurang baik, mungkin capek',
                ]
            ],
            10 => [ // Akademik bermasalah
                'targets' => [5,1],
                'texts' => [
                    'dia ngerti materi lebih cepet, aku tanya dia',
                    'dia bantuin aku pelajaran tadi',
                    'dia juga kesulitan pelajaran tapi tetap coba',
                    'dia keliatan bingung pas kelas tadi',
                    'temanku semangat belajar, aku ikut dikit',
                ]
            ],
        ];

        // Helper Insert
        $insertLapor = function ($from, $to, $date, $texts) {
            DB::table('input_siswas')->insert([
                'siswa_kelas_id' => $from,
                'siswa_dilapor_kelas_id' => $to,
                'is_friend' => 1,
                'tanggal' => $date,
                'teks' => $texts[array_rand($texts)],
                'gambar' => null,
                'status_upload' => 1,
                'meta' => json_encode([
                    'src' => 'flutter',
                    'jenis' => 'laporan'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        // ==========================================
        // (1) 7 HARI TERAKHIR — 3 laporan per minggu
        // ==========================================

        foreach ($mapping as $from => $data) {
            $targets = $data['targets'];
            $texts = $data['texts'];

            // 3 laporan dalam 7 hari (misal: hari -1, -3, -5)
            foreach ([1, 3, 5] as $offset) {
                $date = now()->subDays($offset)->toDateString();
                $target = $targets[array_rand($targets)];
                $insertLapor($from, $target, $date, $texts);
            }
        }

        // ==========================================
        // (2) 1 MINGGU SEBELUMNYA — 3 laporan
        // ==========================================

        foreach ($mapping as $from => $data) {
            foreach ([8,10,12] as $offset) {
                $date = now()->subDays($offset)->toDateString();
                $target = $data['targets'][array_rand($data['targets'])];
                $insertLapor($from, $target, $date, $data['texts']);
            }
        }

        // ==========================================
        // (3) 1 BULAN — 3 laporan diawal, tengah, akhir
        // ==========================================

        foreach ($mapping as $from => $data) {
            foreach ([20,30,40] as $offset) {
                $date = now()->subDays($offset)->toDateString();
                $target = $data['targets'][array_rand($data['targets'])];
                $insertLapor($from, $target, $date, $data['texts']);
            }
        }

        // ==========================================
        // (4) 1 TAHUN — 3 laporan acak
        // ==========================================

        foreach ($mapping as $from => $data) {
            foreach ([100,200,300] as $offset) {
                $date = now()->subDays($offset)->toDateString();
                $target = $data['targets'][array_rand($data['targets'])];
                $insertLapor($from, $target, $date, $data['texts']);
            }
        }

        echo "Lapor Teman Seeder completed.\n";
    }
}
