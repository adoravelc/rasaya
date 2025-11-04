<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterRekomendasi;
use App\Models\KategoriMasalah;

class MasterRekomendasiPivotSeeder extends Seeder
{
    public function run(): void
    {
        // Build lookup maps for categories: by lowercase name and by KODE
        $cats = KategoriMasalah::all();
        $byName = $cats->keyBy(fn($c) => mb_strtolower(trim($c->nama)));
        $byKode = $cats->keyBy(fn($c) => strtoupper(trim($c->kode)));

        // Token -> KODE heuristics to catch partial tags
        $tokenToKode = [
            'akademik' => 'AKD',
            'disiplin' => 'DIS', 'tata tertib' => 'DIS',
            'kesehatan mental' => 'EMO', 'emosi' => 'EMO', 'mental' => 'EMO',
            'sosial' => 'SOS', 'pergaulan' => 'SOS',
            'keluarga' => 'KEL', 'pola asuh' => 'KEL',
            'fisik' => 'FIS', 'gaya hidup' => 'FIS',
            'relasi' => 'REL', 'percintaan' => 'REL',
            'karier' => 'KAR', 'masa depan' => 'KAR',
            'digital' => 'DWB', 'wellbeing' => 'DWB',
            'keamanan' => 'KAM', 'keselamatan' => 'KAM',
        ];

        MasterRekomendasi::chunk(100, function ($masters) use ($byName, $byKode, $tokenToKode) {
            foreach ($masters as $m) {
                // Normalize tags from JSON array or comma-separated string
                $tags = collect(is_array($m->tags) ? $m->tags : explode(',', (string) ($m->tags ?? '')))
                    ->map(fn($t) => mb_strtolower(trim((string)$t)))
                    ->filter()->unique();

                $attachIds = collect();

                // 1) Exact match by category name
                foreach ($tags as $t) {
                    $cat = $byName[$t] ?? null;
                    if ($cat) $attachIds->push($cat->id);
                }

                // 2) Match by KODE if tag equals a known code
                foreach ($tags as $t) {
                    $code = strtoupper($t);
                    $cat = $byKode[$code] ?? null;
                    if ($cat) $attachIds->push($cat->id);
                }

                // 3) Heuristic mapping: if tag contains a known token, map to its KODE
                foreach ($tags as $t) {
                    foreach ($tokenToKode as $token => $kode) {
                        if (str_contains($t, $token)) {
                            $cat = $byKode[$kode] ?? null;
                            if ($cat) $attachIds->push($cat->id);
                        }
                    }
                }

                $ids = $attachIds->filter()->unique()->values()->all();
                if (!empty($ids)) {
                    $m->kategoris()->syncWithoutDetaching($ids);
                }
            }
        });
    }
}
