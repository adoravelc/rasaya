<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterRekomendasi;
use App\Models\KategoriMasalah;

class FixRelasiRekomendasiSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ambil semua kategori di DB
        $allCats = KategoriMasalah::all();
        
        // Buat mapping nama ke ID (lowercase)
        $catMapName = $allCats->pluck('id', 'nama')->mapWithKeys(fn($id, $name) => [strtolower($name) => $id]);
        
        // Buat mapping KODE Kategori ke ID (jika ada kolom kode di kategori_masalahs)
        // Kalau gak ada kolom kode, kita pakai nama mapping manual di bawah
        $catMapCode = $allCats->pluck('id', 'kode')->mapWithKeys(fn($id, $code) => [strtoupper($code) => $id]);

        $this->command->info("=== MAPPING KODE -> KATEGORI ID ===");

        // 2. Mapping Manual Prefix Kode Rekomendasi -> Nama Kategori di DB Kamu
        // Ini harus SAMA PERSIS dengan nama yang muncul di terminal tadi
        $prefixMap = [
            'SAKD' => 'Stres Akademik',
            'KSOS' => 'Kecemasan Sosial',
            'DPRN' => 'Depresi Ringan',
            'GTDR' => 'Gangguan Tidur',
            'BTMK' => 'Bullying Tatap Muka',
            'CBUL' => 'Cyberbullying',
            'TTSP' => 'Tekanan Teman Sebaya',
            'KISO' => 'Kesepian / Isolasi',
            'KOTH' => 'Konflik Orang Tua / Broken Home',
            'TPKL' => 'Tekanan Prestasi Keluarga',
            'MBRD' => 'Motivasi Belajar Rendah',
            'PRTG' => 'Prokrastinasi Tugas',
            'KBLN' => 'Ketidakhadiran / Bolos',
            'KAFK' => 'Kurang Aktivitas Fisik',
            'PTGB' => 'Pola Tidur & Gizi Buruk',
            'KPCR' => 'Konflik Percintaan',
            'PTKH' => 'Putus & Kehilangan',
            'KJUR' => 'Kebingungan Jurusan / Karier',
            'HEKO' => 'Hambatan Ekonomi',
            'PTTB' => 'Pelanggaran Tata Tertib',
            'MWBK' => 'Manajemen Waktu Buruk',
            'OMSO' => 'Overuse Media Sosial',
            'GBRL' => 'Game Berlebihan',
            'KFVD' => 'Kekerasan Fisik / Verbal oleh Dewasa',
            'PBGD' => 'Perundungan Berbasis Gender',
            // Tambahan untuk Bulk Seeder yang pake AKD, EMO, SOS, dll
            'AKD'  => ['Stres Akademik', 'Motivasi Belajar Rendah', 'Prokrastinasi Tugas'], // Bisa pilih salah satu default
            'EMO'  => ['Depresi Ringan', 'Kecemasan Sosial'],
            'SOS'  => ['Bullying Tatap Muka', 'Kesepian / Isolasi'],
            'DIS'  => ['Pelanggaran Tata Tertib', 'Ketidakhadiran / Bolos'],
        ];

        $rekomendasis = MasterRekomendasi::all();
        $fixedCount = 0;

        foreach ($rekomendasis as $rec) {
            // Ambil Prefix Kode (misal SAKD_01 -> SAKD)
            $parts = explode('_', $rec->kode);
            $prefix = strtoupper($parts[0]);

            $catId = null;

            // STRATEGI 1: Cek Mapping Prefix ke Nama Kategori
            if (isset($prefixMap[$prefix])) {
                $targetName = $prefixMap[$prefix];
                
                // Kalau targetnya array (kasus AKD/EMO), ambil yang pertama dulu atau cari yang cocok
                if (is_array($targetName)) {
                    $targetName = $targetName[0]; 
                }

                $catId = $catMapName[strtolower($targetName)] ?? null;
            }

            // STRATEGI 2: Cek langsung ke Kode Kategori (kalau kolom kode diisi benar di DB)
            if (!$catId) {
                $catId = $catMapCode[$prefix] ?? null;
            }

            // STRATEGI 3: Fallback ke Topik JSON (seperti sebelumnya, untuk sisa-sisa)
            if (!$catId) {
                $rules = is_string($rec->rules) ? json_decode($rec->rules, true) : $rec->rules;
                $topikRaw = $rules['topik'] ?? null;
                if ($topikRaw) {
                     // Coba cari nama kategori yang mengandung kata dari topik
                     foreach ($catMapName as $name => $id) {
                        if (str_contains(strtolower($topikRaw), $name) || str_contains($name, strtolower($topikRaw))) {
                            $catId = $id;
                            break;
                        }
                    }
                }
            }

            // EKSEKUSI
            if ($catId) {
                $rec->kategoris()->syncWithoutDetaching([$catId]);
                $fixedCount++;
            } else {
                $this->command->error("X Masih Gagal: Kode '{$rec->kode}' (Prefix: $prefix) tidak nemu jodoh kategorinya.");
            }
        }

        $this->command->info("\nSelesai! Berhasil memperbaiki relasi untuk {$fixedCount} rekomendasi.");
    }
}