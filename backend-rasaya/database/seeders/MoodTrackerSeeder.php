<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class MoodTrackerSeeder extends Seeder
{
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('pemantauan_emosi_siswas')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $today = now()->toDateString();

        // ==========================================
        // 10 PERSONALITIES (ID 1–10)
        // Setiap personality sekarang punya:
        //  - possible_notes_pagi (array)
        //  - possible_notes_sore (array)
        // Catatan akan muncul HANYA 20% secara random
        // ==========================================

        $personalities = [
            1 => [ // Terlambat kronis
                'pagi' => [2, 3, 1, 4, 2, 3, 2],
                'sore' => [5, 6, 6, 7, 5, 6, 5],
                'random_pagi' => [1, 2, 3, 4],
                'random_sore' => [5, 6, 7],
                'notes_pagi' => ['ngantuk', 'kurang tidur', 'lelah', 'kesiangan'],
                'notes_sore' => ['lebih enakan', 'sudah segar', 'lebih tenang'],
            ],
            2 => [ // Broken home
                'pagi' => [3, 5, 2, 4, 1, 6, 3],
                'sore' => [4, 3, 5, 2, 3, 4, 3],
                'random_pagi' => [1, 2, 3, 4, 5, 6],
                'random_sore' => [2, 3, 4, 5],
                'notes_pagi' => ['kepikiran rumah', 'sedikit sedih', 'kurang fokus'],
                'notes_sore' => ['cemas sedikit', 'lebih tenang', 'masih kepikiran'],
            ],
            3 => [ // Introvert
                'pagi' => [5, 4, 6, 5, 5, 4, 5],
                'sore' => [4, 5, 5, 5, 4, 5, 4],
                'random_pagi' => [4, 5, 6],
                'random_sore' => [4, 5],
                'notes_pagi' => ['ok', 'baik', 'normal'],
                'notes_sore' => ['biasa saja', 'stabil'],
            ],
            4 => [ // Ekstrovert
                'pagi' => [7, 8, 9, 7, 8, 9, 8],
                'sore' => [8, 9, 9, 8, 7, 9, 9],
                'random_pagi' => [7, 8, 9],
                'random_sore' => [8, 9],
                'notes_pagi' => ['seru sama teman', 'happy', 'excited'],
                'notes_sore' => ['ramai', 'seru banget', 'hari menyenangkan'],
            ],
            5 => [ // Perfeksionis
                'pagi' => [7, 6, 8, 7, 7, 6, 8],
                'sore' => [4, 3, 5, 6, 4, 5, 3],
                'random_pagi' => [6, 7, 8],
                'random_sore' => [3, 4, 5, 6],
                'notes_pagi' => ['banyak tugas', 'persiapan ujian', 'target tinggi'],
                'notes_sore' => ['takut nilainya jelek', 'masih belajar', 'deg-degan'],
            ],
            6 => [ // Pemalu
                'pagi' => [4, 5, 3, 4, 4, 3, 5],
                'sore' => [5, 4, 4, 3, 4, 3, 4],
                'random_pagi' => [3, 4, 5],
                'random_sore' => [3, 4, 5],
                'notes_pagi' => ['deg-degan', 'malu ngomong', 'kurang percaya diri'],
                'notes_sore' => ['capek ngomong', 'takut salah'],
            ],
            7 => [ // Overthinking
                'pagi' => [8, 2, 9, 3, 7, 1, 6],
                'sore' => [6, 5, 2, 8, 3, 7, 4],
                'random_pagi' => [1, 2, 3, 7, 8, 9],
                'random_sore' => [2, 3, 4, 5, 6, 7, 8],
                'notes_pagi' => ['kepikiran banyak hal', 'gak tenang', 'banyak worry'],
                'notes_sore' => ['masih kepikiran', 'mendingan dikit'],
            ],
            8 => [ // Disiplin buruk
                'pagi' => [4, 2, 6, 3, 1, 5, 2],
                'sore' => [3, 1, 4, 6, 2, 5, 3],
                'random_pagi' => [1, 2, 3, 4, 5, 6],
                'random_sore' => [1, 2, 3, 4, 5, 6],
                'notes_pagi' => ['malas sekolah', 'bosen', 'ngantuk'],
                'notes_sore' => ['pengen pulang', 'malas banget'],
            ],
            9 => [ // Helper
                'pagi' => [8, 9, 8, 7, 8, 9, 9],
                'sore' => [7, 8, 8, 9, 8, 7, 9],
                'random_pagi' => [7, 8, 9],
                'random_sore' => [7, 8, 9],
                'notes_pagi' => ['hari ini lumayan', 'bantu teman tadi', 'positif'],
                'notes_sore' => ['senang bantu orang', 'baik hari ini'],
            ],
            10 => [ // Akademik bermasalah
                'pagi' => [6, 5, 7, 6, 6, 5, 7],
                'sore' => [3, 4, 2, 3, 2, 3, 4],
                'random_pagi' => [5, 6, 7],
                'random_sore' => [2, 3, 4],
                'notes_pagi' => ['masih belajar', 'agak pusing materi'],
                'notes_sore' => ['pelajaran susah', 'bingung', 'ketinggalan'],
            ],
        ];

        // ==========================================
        // Helper: generate note 20% chance
        // ==========================================
        $generateNote = function ($possibleNotes) {
            return (rand(1, 100) <= 20)
                ? $possibleNotes[array_rand($possibleNotes)]
                : null;
        };

        // ==========================================
        // Helper: insert pagi & sore sekaligus
        // ==========================================
        $insertMood = function ($siswaId, $date, $p, $generateNote) {
            DB::table('pemantauan_emosi_siswas')->insert([
                [
                    'siswa_kelas_id' => $siswaId,
                    'tanggal' => $date,
                    'sesi' => 'pagi',
                    'skor' => $p['random_pagi'][array_rand($p['random_pagi'])],
                    'catatan' => $generateNote($p['notes_pagi']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'siswa_kelas_id' => $siswaId,
                    'tanggal' => $date,
                    'sesi' => 'sore',
                    'skor' => $p['random_sore'][array_rand($p['random_sore'])],
                    'catatan' => $generateNote($p['notes_sore']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        };

        // ==========================================
        // (1) 7 HARI TERAKHIR (pattern fix personality)
        // ==========================================

        foreach ($personalities as $siswaId => $p) {
            for ($i = 0; $i < 7; $i++) {
                $date = now()->subDays(6 - $i)->toDateString();

                DB::table('pemantauan_emosi_siswas')->insert([
                    [
                        'siswa_kelas_id' => $siswaId,
                        'tanggal' => $date,
                        'sesi' => 'pagi',
                        'skor' => $p['pagi'][$i],
                        'catatan' => $generateNote($p['notes_pagi']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'siswa_kelas_id' => $siswaId,
                        'tanggal' => $date,
                        'sesi' => 'sore',
                        'skor' => $p['sore'][$i],
                        'catatan' => $generateNote($p['notes_sore']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            }
        }

        // ==========================================
        // (2) 1 MINGGU SEBELUMNYA
        // ==========================================

        foreach ($personalities as $siswaId => $p) {
            for ($i = 8; $i <= 14; $i++) {
                $date = now()->subDays($i)->toDateString();
                $insertMood($siswaId, $date, $p, $generateNote);
            }
        }

        // ==========================================
        // (3) 1 BULAN SEBELUMNYA
        // ==========================================

        foreach ($personalities as $siswaId => $p) {
            for ($i = 15; $i <= 44; $i++) {
                $date = now()->subDays($i)->toDateString();
                $insertMood($siswaId, $date, $p, $generateNote);
            }
        }

        // ==========================================
        // (4) 1 TAHUN SEBELUMNYA
        // ==========================================

        foreach ($personalities as $siswaId => $p) {
            for ($i = 50; $i <= 415; $i++) {
                $date = now()->subDays($i)->toDateString();
                $insertMood($siswaId, $date, $p, $generateNote);
            }
        }

        echo "Mood Tracker Seeder (20% notes) completed.\n";
    }
}
