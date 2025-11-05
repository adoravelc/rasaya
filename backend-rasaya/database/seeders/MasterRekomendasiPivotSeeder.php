<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterRekomendasi;
use App\Models\KategoriMasalah;

class MasterRekomendasiPivotSeeder extends Seeder
{
    public function run(): void
    {
        // Build lookup maps for categories
        $cats = KategoriMasalah::all();
        $byName = $cats->keyBy(fn($c) => mb_strtolower(trim($c->nama)));
        $byKode = $cats->keyBy(fn($c) => strtoupper(trim($c->kode)));

        // Token → KODE mapping (updated to match latest KategoriMasalahSeeder)
        $tokenToKode = [
            'stres akademik' => 'SAKD',
            'akademik' => 'SAKD',
            'stres' => 'SAKD',

            'kecemasan sosial' => 'KSOS',
            'sosial cemas' => 'KSOS',
            'cemas sosial' => 'KSOS',

            'depresi' => 'DPRN',
            'down' => 'DPRN',
            'sedih' => 'DPRN',

            'gangguan tidur' => 'GTDR',
            'tidur' => 'GTDR',
            'insomnia' => 'GTDR',

            'bullying' => 'BTMK',
            'perundungan tatap muka' => 'BTMK',
            'ejekan' => 'BTMK',

            'cyberbullying' => 'CBUL',
            'perundungan online' => 'CBUL',
            'dunia maya' => 'CBUL',

            'teman sebaya' => 'TTSP',
            'tekanan teman' => 'TTSP',
            'peer pressure' => 'TTSP',

            'kesepian' => 'KISO',
            'isolasi' => 'KISO',
            'terasing' => 'KISO',

            'konflik orang tua' => 'KOTH',
            'broken home' => 'KOTH',
            'keluarga tidak harmonis' => 'KOTH',

            'tekanan prestasi' => 'TPKL',
            'prestasi keluarga' => 'TPKL',

            'motivasi belajar' => 'MBRD',
            'belajar malas' => 'MBRD',

            'prokrastinasi' => 'PRTG',
            'menunda tugas' => 'PRTG',

            'bolos' => 'KBLN',
            'ketidakhadiran' => 'KBLN',

            'aktivitas fisik' => 'KAFK',
            'olahraga' => 'KAFK',

            'pola tidur' => 'PTGB',
            'gizi buruk' => 'PTGB',
            'makan tidak sehat' => 'PTGB',

            'konflik percintaan' => 'KPCR',
            'masalah cinta' => 'KPCR',
            'hubungan asmara' => 'KPCR',

            'putus' => 'PTKH',
            'kehilangan' => 'PTKH',

            'jurusan' => 'KJUR',
            'karier' => 'KJUR',
            'masa depan' => 'KJUR',

            'ekonomi' => 'HEKO',
            'keuangan' => 'HEKO',
            'biaya sekolah' => 'HEKO',

            'tata tertib' => 'PTTB',
            'pelanggaran' => 'PTTB',
            'disiplin' => 'PTTB',

            'manajemen waktu' => 'MWBK',
            'waktu buruk' => 'MWBK',

            'media sosial' => 'OMSO',
            'sosial media' => 'OMSO',
            'gadget' => 'OMSO',

            'game' => 'GBRL',
            'kecanduan game' => 'GBRL',

            'kekerasan fisik' => 'KFVD',
            'kekerasan verbal' => 'KFVD',
            'oleh dewasa' => 'KFVD',

            'berbasis gender' => 'PBGD',
            'gender' => 'PBGD',
            'seksual' => 'PBGD',
        ];

        // Attach pivot relations
        MasterRekomendasi::chunk(100, function ($masters) use ($byName, $byKode, $tokenToKode) {
            foreach ($masters as $m) {
                $rawTags = $m->tags ?? [];

                // Normalize tags input
                if (is_array($rawTags)) {
                    $tagItems = $rawTags;
                } else {
                    $rawStr = (string) $rawTags;
                    $decoded = json_decode($rawStr, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $tagItems = $decoded;
                    } elseif ($rawStr !== '') {
                        $tagItems = explode(',', $rawStr);
                    } else {
                        $tagItems = [];
                    }
                }

                // Include topik/subtopik if available
                $rules = $m->rules ?? [];
                $rulesArr = is_string($rules)
                    ? (json_decode($rules, true) ?? [])
                    : (is_array($rules) ? $rules : []);
                if (!empty($rulesArr)) {
                    if (!empty($rulesArr['topik']))
                        $tagItems[] = $rulesArr['topik'];
                    if (!empty($rulesArr['subtopik']))
                        $tagItems[] = $rulesArr['subtopik'];
                }

                $tags = collect($tagItems)
                    ->map(fn($t) => mb_strtolower(trim((string) $t)))
                    ->filter()
                    ->unique();

                $attachIds = collect();

                // 1) Exact match by name
                foreach ($tags as $t) {
                    $cat = $byName[$t] ?? null;
                    if ($cat)
                        $attachIds->push($cat->id);
                }

                // 2) Match by code
                foreach ($tags as $t) {
                    $code = strtoupper($t);
                    $cat = $byKode[$code] ?? null;
                    if ($cat)
                        $attachIds->push($cat->id);
                }

                // 3) Match by token substring
                foreach ($tags as $t) {
                    foreach ($tokenToKode as $token => $kode) {
                        if (str_contains($t, $token)) {
                            $cat = $byKode[$kode] ?? null;
                            if ($cat)
                                $attachIds->push($cat->id);
                        }
                    }
                }

                // 4) Match by kode prefix
                if (!empty($m->kode)) {
                    $prefix = strtoupper(strtok((string) $m->kode, '_'));
                    $cat = $byKode[$prefix] ?? null;
                    if ($cat)
                        $attachIds->push($cat->id);
                }

                $ids = $attachIds->filter()->unique()->values()->all();
                if (!empty($ids)) {
                    $m->kategoris()->syncWithoutDetaching($ids);
                }
            }
        });
    }
}
