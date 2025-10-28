<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterRekomendasi;
use App\Models\KategoriMasalah;

class MasterRekomendasiPivotSeeder extends Seeder
{
    public function run(): void
    {
        $catMap = KategoriMasalah::all()->keyBy(fn($c) => mb_strtolower(trim($c->nama)));

        MasterRekomendasi::chunk(100, function ($masters) use ($catMap) {
            foreach ($masters as $m) {
                // Try JSON array column 'tags' (fallback: comma-separated string)
                $tags = collect(is_array($m->tags) ? $m->tags : explode(',', (string) ($m->tags ?? '')))
                    ->map(fn($t) => mb_strtolower(trim($t)))
                    ->filter()->unique();

                $ids = $tags->map(fn($t) => $catMap[$t]->id ?? null)->filter()->values()->all();

                if (!empty($ids)) {
                    $m->kategoris()->syncWithoutDetaching($ids);
                }
            }
        });
    }
}
