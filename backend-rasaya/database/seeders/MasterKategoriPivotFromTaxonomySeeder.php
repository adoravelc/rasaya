<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\MasterKategoriMasalah;
use App\Models\KategoriMasalah;

class MasterKategoriPivotFromTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        // Attempt to read taxonomy.json from sibling project folder
        $path = base_path('..' . DIRECTORY_SEPARATOR . 'ml-rasaya' . DIRECTORY_SEPARATOR . 'taxonomy.json');
        if (!File::exists($path)) {
            $this->command?->warn("taxonomy.json not found at: {$path}. Skipping pivot seeding.");
            return;
        }
        $json = json_decode(File::get($path), true);
        if (!is_array($json)) {
            $this->command?->warn('Invalid taxonomy.json structure.');
            return;
        }
        $topics = $json['topics'] ?? [];
        $attached = 0; $missing = 0; $topicMiss = 0; $katMiss = 0;
        foreach ($topics as $tp) {
            $topicId = $tp['id'] ?? null; // e.g., MENTAL_EMOSI
            if (!$topicId) { continue; }
            $master = MasterKategoriMasalah::where('kode', $topicId)->first();
            if (!$master) { $topicMiss++; continue; }
            foreach (($tp['subtopics'] ?? []) as $st) {
                $code = $st['code'] ?? null; // must match kategori_masalahs.kode
                if (!$code) { $missing++; continue; }
                $kat = KategoriMasalah::where('kode', $code)->first();
                if (!$kat) { $katMiss++; continue; }
                $master->subkategoris()->syncWithoutDetaching([$kat->id]);
                $attached++;
            }
        }
        $this->command?->info("Pivot seeding done: attached={$attached}, missing_code={$missing}, missing_master={$topicMiss}, missing_kategori={$katMiss}");
    }
}
