<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TahunAjaran;

class TahunAjaranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['nama' => '2023/2024', 'mulai' => '2023-07-01', 'selesai' => '2024-06-30', 'is_active' => false],
            ['nama' => '2024/2025', 'mulai' => '2024-07-01', 'selesai' => '2025-06-30', 'is_active' => true],
            ['nama' => '2025/2026', 'mulai' => '2025-07-01', 'selesai' => '2026-06-30', 'is_active' => false],
        ];

        foreach ($data as $ta) {
            TahunAjaran::updateOrCreate(['nama' => $ta['nama']], $ta);
        }

        // memastikan hanya satu yang aktif
        $activeId = TahunAjaran::where('is_active', true)->value('id');
        TahunAjaran::where('id', '!=', $activeId)->update(['is_active' => false]);
    }
}
