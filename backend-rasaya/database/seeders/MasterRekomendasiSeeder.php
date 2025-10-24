<?php

namespace Database\Seeders;

use App\Models\MasterRekomendasi;
use Illuminate\Database\Seeder;

class MasterRekomendasiSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'kode' => 'ATT-LATE',
                'judul' => 'Konseling kedisiplinan hadir',
                'deskripsi' => 'Bahas kebiasaan telat/bolos, identifikasi penyebab, buat kontrak kehadiran.',
                'severity' => 'medium',
                'rules' => ['min_neg_score' => -0.10, 'any_keywords' => ['telat', 'bolos', 'alfa', 'alpha', 'terlambat']],
                'tags' => ['disiplin', 'kehadiran'],
            ],
            [
                'kode' => 'ACAD-OVERLOAD',
                'judul' => 'Manajemen tugas & beban belajar',
                'deskripsi' => 'Ajarkan teknik manajemen waktu, prioritas, dan komunikasi tugas ke guru mapel.',
                'severity' => 'low',
                'rules' => ['min_neg_score' => -0.05, 'any_keywords' => ['pr', 'tugas', 'capek', 'kelelahan', 'beban']],
                'tags' => ['akademik', 'motivasi'],
            ],
            [
                'kode' => 'SOC-CONFLICT',
                'judul' => 'Mediasi konflik sosial',
                'deskripsi' => 'Fasilitasi dialog damai jika ada cekcok/berantem/perundungan.',
                'severity' => 'high',
                'rules' => ['min_neg_score' => -0.15, 'any_keywords' => ['berantem', 'cekcok', 'bully', 'perundungan', 'ribut']],
                'tags' => ['sosial', 'keamanan'],
            ],
        ];

        foreach ($data as $d) {
            MasterRekomendasi::updateOrCreate(['kode' => $d['kode']], $d);
        }
    }
}

